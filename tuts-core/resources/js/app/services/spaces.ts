import { apiFetch } from "@/app/services/api";

export interface StudySpace {
    id: number;
    name: string;
    description?: string | null;
    cover?: string | null;
    color?: string | null;
    chats_count?: number | null;
    materials_count?: number | null;
    folders_count?: number | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface SpaceChatSummary {
    chat_id: number;
    title?: string | null;
    messages_count?: number | null;
    folder_id?: number | null;
    folder_name?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface SpaceMaterial {
    id: number;
    space_id?: number | null;
    folder_id?: number | null;
    folder_name?: string | null;
    original_name: string;
    stored_name?: string | null;
    path?: string | null;
    mime_type?: string | null;
    extension?: string | null;
    size?: number | null;
    human_size?: string | null;
    download_url: string;
    view_url?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface SpaceFolder {
    id: number;
    space_id?: number | null;
    name: string;
    type: "folder" | "topic" | "module" | "category";
    color?: string | null;
    chats_count?: number | null;
    materials_count?: number | null;
    created_at?: string | null;
    updated_at?: string | null;
}

interface SpacesResponse {
    status: string;
    spaces: StudySpace[];
}

interface SpaceResponse {
    status: string;
    space: StudySpace;
}

interface SpaceDetailResponse {
    status: string;
    space: StudySpace;
    latest_chats?: SpaceChatSummary[];
}

interface SpaceConversationsResponse {
    status: string;
    conversations?: SpaceChatSummary[];
    chats?: SpaceChatSummary[];
}

interface SpaceConversationResponse {
    status: string;
    conversation?: SpaceChatSummary;
    chat?: SpaceChatSummary;
}

interface SpaceMaterialsResponse {
    status: string;
    materials?: SpaceMaterial[];
}

interface SpaceMaterialResponse {
    status: string;
    material: SpaceMaterial;
}

interface SpaceFoldersResponse {
    status: string;
    folders?: SpaceFolder[];
}

interface SpaceFolderResponse {
    status: string;
    folder: SpaceFolder;
}

export interface SpacePayload {
    name: string;
    description?: string | null;
    cover?: string | null;
    color?: string | null;
}

export interface SpaceFolderPayload {
    name: string;
    type?: SpaceFolder["type"];
    color?: string | null;
}

function normalizeId(id: number | string): string {
    return String(id);
}

export async function fetchSpaces(): Promise<StudySpace[]> {
    const response = await apiFetch<SpacesResponse>("/api/spaces");
    return response.spaces ?? [];
}

export async function fetchSpace(
    id: number | string,
): Promise<SpaceDetailResponse> {
    return await apiFetch<SpaceDetailResponse>(
        `/api/spaces/${normalizeId(id)}`,
    );
}

export async function createSpace(payload: SpacePayload): Promise<StudySpace> {
    const response = await apiFetch<SpaceResponse>("/api/spaces", {
        method: "POST",
        json: payload,
    });

    return response.space;
}

export async function updateSpace(
    id: number,
    payload: SpacePayload,
): Promise<StudySpace> {
    const response = await apiFetch<SpaceResponse>(`/api/spaces/${id}`, {
        method: "PATCH",
        json: payload,
    });

    return response.space;
}

export async function deleteSpace(id: number): Promise<void> {
    await apiFetch<{ status: string }>(`/api/spaces/${id}`, {
        method: "DELETE",
    });
}

export async function fetchSpaceConversations(
    spaceId: number | string,
): Promise<SpaceChatSummary[]> {
    const response = await apiFetch<SpaceConversationsResponse>(
        `/api/spaces/${normalizeId(spaceId)}/conversations`,
    );

    return response.conversations ?? response.chats ?? [];
}

export async function createSpaceConversation(
    spaceId: number | string,
    title: string,
    folderId?: number | null,
): Promise<SpaceChatSummary> {
    const response = await apiFetch<SpaceConversationResponse>(
        `/api/spaces/${normalizeId(spaceId)}/conversations`,
        {
            method: "POST",
            json: {
                title,
                folder_id: folderId ?? null,
            },
        },
    );

    const chat = response.conversation ?? response.chat;

    if (!chat) {
        throw new Error("A API não devolveu a conversa criada.");
    }

    return chat;
}

export async function moveSpaceConversationToFolder(
    spaceId: number | string,
    chatId: number | string,
    folderId?: number | null,
): Promise<SpaceChatSummary> {
    const response = await apiFetch<SpaceConversationResponse>(
        `/api/spaces/${normalizeId(spaceId)}/conversations/${normalizeId(chatId)}/folder`,
        {
            method: "PATCH",
            json: {
                folder_id: folderId ?? null,
            },
        },
    );

    const chat = response.conversation ?? response.chat;

    if (!chat) {
        throw new Error("A API não devolveu a conversa atualizada.");
    }

    return chat;
}

export async function fetchSpaceMaterials(
    spaceId: number | string,
): Promise<SpaceMaterial[]> {
    const response = await apiFetch<SpaceMaterialsResponse>(
        `/api/spaces/${normalizeId(spaceId)}/materials`,
    );

    return response.materials ?? [];
}

export async function uploadSpaceMaterial(
    spaceId: number | string,
    file: File,
    name?: string | null,
    folderId?: number | null,
): Promise<SpaceMaterial> {
    const formData = new FormData();

    formData.append("file", file);

    if (name) {
        formData.append("name", name);
    }

    if (folderId !== null && folderId !== undefined) {
        formData.append("folder_id", String(folderId));
    }

    const response = await apiFetch<SpaceMaterialResponse>(
        `/api/spaces/${normalizeId(spaceId)}/materials`,
        {
            method: "POST",
            body: formData,
        },
    );

    return response.material;
}

export async function moveSpaceMaterialToFolder(
    spaceId: number | string,
    materialId: number | string,
    folderId?: number | null,
): Promise<SpaceMaterial> {
    const response = await apiFetch<SpaceMaterialResponse>(
        `/api/spaces/${normalizeId(spaceId)}/materials/${normalizeId(materialId)}/folder`,
        {
            method: "PATCH",
            json: {
                folder_id: folderId ?? null,
            },
        },
    );

    return response.material;
}

export async function deleteSpaceMaterial(
    spaceId: number | string,
    materialId: number | string,
): Promise<void> {
    await apiFetch<{ status: string }>(
        `/api/spaces/${normalizeId(spaceId)}/materials/${normalizeId(materialId)}`,
        {
            method: "DELETE",
        },
    );
}

export async function fetchSpaceFolders(
    spaceId: number | string,
): Promise<SpaceFolder[]> {
    const response = await apiFetch<SpaceFoldersResponse>(
        `/api/spaces/${normalizeId(spaceId)}/folders`,
    );

    return response.folders ?? [];
}

export async function createSpaceFolder(
    spaceId: number | string,
    payload: SpaceFolderPayload,
): Promise<SpaceFolder> {
    const response = await apiFetch<SpaceFolderResponse>(
        `/api/spaces/${normalizeId(spaceId)}/folders`,
        {
            method: "POST",
            json: payload,
        },
    );

    return response.folder;
}

export async function updateSpaceFolder(
    spaceId: number | string,
    folderId: number | string,
    payload: Partial<SpaceFolderPayload>,
): Promise<SpaceFolder> {
    const response = await apiFetch<SpaceFolderResponse>(
        `/api/spaces/${normalizeId(spaceId)}/folders/${normalizeId(folderId)}`,
        {
            method: "PATCH",
            json: payload,
        },
    );

    return response.folder;
}

export async function deleteSpaceFolder(
    spaceId: number | string,
    folderId: number | string,
): Promise<void> {
    await apiFetch<{ status: string }>(
        `/api/spaces/${normalizeId(spaceId)}/folders/${normalizeId(folderId)}`,
        {
            method: "DELETE",
        },
    );
}
