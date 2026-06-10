function csrfFromCookie(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

function csrfFromMeta(): string | null {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? null
    );
}

async function refreshCsrfCookie(): Promise<void> {
    await fetch("/sanctum/csrf-cookie", {
        method: "GET",
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
        },
    });
}

export type ChatPreference =
    | "default"
    | "visual"
    | "plano"
    | "quiz"
    | "feynman";

export type ChatContextType = 'uc' | 'space' | 'temporary';

export interface StreamChatPayload {
    texto: string;
    uc?: string | null;
    chat_id?: number | string | null;
    context_type?: ChatContextType;
    space_id?: number | string | null;
    folder_id?: number | string | null;
    preferencia?: ChatPreference;
    imagem?: File | null;
}

export interface StreamCallbacks {
    onChunk?: (chunk: string) => void;
    onMeta?: (payload: Record<string, unknown>) => void;
    onChatId?: (chatId: number) => void;
    onStatus?: (status: string) => void;
    onDone?: () => void;
    onError?: (error: Error) => void;
}

function parseErrorText(raw: string): string {
    try {
        const parsed = JSON.parse(raw);
        return parsed.message ?? parsed.detail ?? raw;
    } catch {
        return raw;
    }
}

function buildCsrfHeaders(): Headers {
    const headers = new Headers({
        Accept: "text/event-stream",
        "X-Requested-With": "XMLHttpRequest",
    });

    const metaToken = csrfFromMeta();
    const cookieToken = csrfFromCookie();

    if (metaToken) {
        headers.set("X-CSRF-TOKEN", metaToken);
    }

    if (cookieToken) {
        headers.set("X-XSRF-TOKEN", cookieToken);
    }

    return headers;
}

function buildFormData(payload: StreamChatPayload): FormData {
    const formData = new FormData();

    formData.append("texto", payload.texto);
    formData.append("context_type", payload.context_type ?? "uc");
    formData.append("preferencia", payload.preferencia ?? "default");

    if (payload.uc && payload.uc.trim() !== "") {
        formData.append("uc", payload.uc);
    }

    if (
        payload.space_id !== null &&
        payload.space_id !== undefined &&
        String(payload.space_id).trim() !== ""
    ) {
        formData.append("space_id", String(payload.space_id));
    }

    if (
        payload.folder_id !== null &&
        payload.folder_id !== undefined &&
        String(payload.folder_id).trim() !== ""
    ) {
        formData.append("folder_id", String(payload.folder_id));
    }

    if (
        payload.chat_id !== null &&
        payload.chat_id !== undefined &&
        String(payload.chat_id).trim() !== ""
    ) {
        formData.append("chat_id", String(payload.chat_id));
    }

    if (payload.imagem) {
        formData.append("imagem", payload.imagem);
    }

    return formData;
}

async function postChatStream(payload: StreamChatPayload): Promise<Response> {
    return fetch("/api/chat/stream", {
        method: "POST",
        body: buildFormData(payload),
        headers: buildCsrfHeaders(),
        credentials: "same-origin",
    });
}

export async function streamChatMessage(
    payload: StreamChatPayload,
    callbacks: StreamCallbacks = {},
): Promise<void> {
    try {
        await refreshCsrfCookie();

        let response = await postChatStream(payload);

        if (response.status === 419) {
            await refreshCsrfCookie();
            response = await postChatStream(payload);
        }

        if (!response.ok) {
            const text = await response.text();
            throw new Error(
                parseErrorText(text) || `Erro HTTP ${response.status}`,
            );
        }

        if (!response.body) {
            throw new Error("O browser não recebeu stream do backend.");
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder("utf-8");
        let buffer = "";

        while (true) {
            const { value, done } = await reader.read();

            if (done) break;

            buffer += decoder.decode(value, { stream: true });

            const lines = buffer.split(/\r?\n/);
            buffer = lines.pop() ?? "";

            for (const line of lines) {
                const trimmed = line.trim();

                if (!trimmed.startsWith("data:")) continue;

                const data = trimmed.slice(5).trim();

                if (data === "[DONE]") {
                    callbacks.onDone?.();
                    return;
                }

                let parsed: unknown;

                try {
                    parsed = JSON.parse(data);
                } catch {
                    callbacks.onChunk?.(data);
                    continue;
                }

                if (!parsed || typeof parsed !== "object") continue;

                const event = parsed as Record<string, unknown>;

                if (typeof event.chat_id === "number") {
                    callbacks.onChatId?.(event.chat_id);
                    callbacks.onMeta?.(event);
                    continue;
                }

                if (typeof event.chunk === "string") {
                    callbacks.onChunk?.(event.chunk);
                    continue;
                }

                if (typeof event.status_msg === "string") {
                    callbacks.onStatus?.(event.status_msg);
                    callbacks.onMeta?.(event);
                    continue;
                }

                callbacks.onMeta?.(event);
            }
        }

        callbacks.onDone?.();
    } catch (error) {
        const err = error instanceof Error ? error : new Error(String(error));
        callbacks.onError?.(err);
        throw err;
    }
}
