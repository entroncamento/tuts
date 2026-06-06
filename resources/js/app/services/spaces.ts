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

interface SpacesResponse {
    status: string;
    spaces: StudySpace[];
}

interface SpaceResponse {
    status: string;
    space: StudySpace;
}

export interface SpacePayload {
    name: string;
    description?: string | null;
    cover?: string | null;
    color?: string | null;
}

export async function fetchSpaces(): Promise<StudySpace[]> {
    const response = await apiFetch<SpacesResponse>("/api/spaces");
    return response.spaces ?? [];
}

export async function createSpace(payload: SpacePayload): Promise<StudySpace> {
    const response = await apiFetch<SpaceResponse>("/api/spaces", {
        method: "POST",
        body: JSON.stringify(payload),
    });

    return response.space;
}

export async function updateSpace(
    id: number,
    payload: SpacePayload,
): Promise<StudySpace> {
    const response = await apiFetch<SpaceResponse>(`/api/spaces/${id}`, {
        method: "PATCH",
        body: JSON.stringify(payload),
    });

    return response.space;
}

export async function deleteSpace(id: number): Promise<void> {
    await apiFetch<{ status: string }>(`/api/spaces/${id}`, {
        method: "DELETE",
    });
}
