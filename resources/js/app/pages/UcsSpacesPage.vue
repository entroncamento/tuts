<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
    FolderPlus,
    MessageCircle,
    MoreVertical,
    Search,
    X,
} from "@lucide/vue";
import UCCard from "@/app/components/UCCard.vue";
import { fetchMySubjects } from "@/app/services/subjects";
import { apiFetch } from "@/app/services/api";
import {
    createSpace,
    fetchSpaces,
    type StudySpace,
} from "@/app/services/spaces";
import { UC_LIST, type UCData } from "@/app/data/ucData";

type TabType = "ucs" | "spaces" | "conversations";

interface UCWithAcademicMeta extends UCData {
    year?: string | null;
    semester?: string | null;
    type?: string | null;
    electiveGroup?: string | null;
    teacherNote?: string | null;
}

interface ChatSummary {
    chat_id: number;
    context_type?: "uc" | "space" | "temporary" | null;
    subject_id?: number | null;
    space_id?: number | null;
    nome_uc: string | null;
    nome_espaco?: string | null;
    title: string | null;
    updated_at: string;
}

interface UcSemesterGroup {
    semester: string;
    items: UCWithAcademicMeta[];
}

interface UcYearGroup {
    year: string;
    semesters: UcSemesterGroup[];
}

const MAX_SPACES_PER_USER = 5;

const router = useRouter();
const route = useRoute();

const activeTab = ref<TabType>("ucs");
const searchQuery = ref("");
const ucs = ref<UCWithAcademicMeta[]>(UC_LIST as UCWithAcademicMeta[]);
const spaces = ref<StudySpace[]>([]);
const chats = ref<ChatSummary[]>([]);
const loading = ref(false);
const savingSpace = ref(false);
const createSpaceOpen = ref(false);
const spaceName = ref("");
const spaceDescription = ref("");
const errorMessage = ref<string | null>(null);

const openSpaceMenuId = ref<number | null>(null);

const editSpaceOpen = ref(false);
const editingSpace = ref<StudySpace | null>(null);
const editSpaceName = ref("");
const editSpaceDescription = ref("");
const savingEditSpace = ref(false);

const deleteSpaceOpen = ref(false);
const spaceToDelete = ref<StudySpace | null>(null);
const deletingSpace = ref(false);

const spacesCount = computed(() => spaces.value.length);
const canCreateSpace = computed(() => spacesCount.value < MAX_SPACES_PER_USER);

watch(
    () => route.query.tab,
    (tabParam) => {
        if (tabParam === "espacos") activeTab.value = "spaces";
        else if (tabParam === "conversas") activeTab.value = "conversations";
        else activeTab.value = "ucs";
    },
    { immediate: true },
);

watch(activeTab, () => {
    searchQuery.value = "";
    errorMessage.value = null;
    closeSpaceMenu();
});

const filteredUcs = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return ucs.value;

    return ucs.value.filter((uc) => {
        const searchable =
            `${uc.name} ${uc.teacher ?? ""} ${uc.year ?? ""} ${uc.semester ?? ""}`.toLowerCase();
        return searchable.includes(q);
    });
});

const groupedUcs = computed<UcYearGroup[]>(() => {
    const yearMap = new Map<string, Map<string, UCWithAcademicMeta[]>>();

    filteredUcs.value.forEach((uc) => {
        const year = cleanAcademicLabel(uc.year, "Ano não definido");
        const semester = cleanAcademicLabel(
            uc.semester,
            "Semestre não definido",
        );

        if (!yearMap.has(year)) {
            yearMap.set(year, new Map());
        }

        const semesterMap = yearMap.get(year);

        if (!semesterMap) return;

        if (!semesterMap.has(semester)) {
            semesterMap.set(semester, []);
        }

        semesterMap.get(semester)?.push(uc);
    });

    return Array.from(yearMap.entries())
        .sort(
            ([yearA], [yearB]) =>
                academicYearOrder(yearA) - academicYearOrder(yearB),
        )
        .map(([year, semesterMap]) => ({
            year,
            semesters: Array.from(semesterMap.entries())
                .sort(
                    ([semesterA], [semesterB]) =>
                        semesterOrder(semesterA) - semesterOrder(semesterB),
                )
                .map(([semester, items]) => ({
                    semester,
                    items: [...items].sort((a, b) =>
                        a.name.localeCompare(b.name, "pt-PT"),
                    ),
                })),
        }));
});

const filteredSpaces = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return spaces.value;

    return spaces.value.filter((space) =>
        `${space.name} ${space.description ?? ""}`.toLowerCase().includes(q),
    );
});

const filteredChats = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return chats.value;

    return chats.value.filter((chat) =>
        `${chat.title ?? ""} ${chat.nome_uc ?? ""} ${chat.nome_espaco ?? ""}`
            .toLowerCase()
            .includes(q),
    );
});

function cleanAcademicLabel(
    value: string | null | undefined,
    fallback: string,
): string {
    const cleaned = String(value ?? "").trim();
    return cleaned !== "" ? cleaned : fallback;
}

function academicYearOrder(year: string): number {
    const match = year.match(/\d+/);
    if (!match) return 999;
    return Number(match[0]);
}

function semesterOrder(semester: string): number {
    const match = semester.match(/\d+/);
    if (!match) return 999;
    return Number(match[0]);
}

function countYearSubjects(yearGroup: UcYearGroup): number {
    return yearGroup.semesters.reduce(
        (total, semester) => total + semester.items.length,
        0,
    );
}

function tabToQuery(tab: TabType): string | undefined {
    if (tab === "spaces") return "espacos";
    if (tab === "conversations") return "conversas";
    return undefined;
}

function changeTab(tab: TabType): void {
    activeTab.value = tab;

    router.replace({
        name: "ucs",
        query: tabToQuery(tab) ? { tab: tabToQuery(tab) } : {},
    });
}

function openCreateSpaceModal(): void {
    activeTab.value = "spaces";

    if (!canCreateSpace.value) {
        errorMessage.value =
            "Atingiste o limite de 5 Espaços. Elimina um Espaço existente para criar outro.";
        createSpaceOpen.value = false;
        return;
    }

    errorMessage.value = null;
    createSpaceOpen.value = true;
}

function toggleSpaceMenu(space: StudySpace, event: MouseEvent): void {
    event.stopPropagation();
    openSpaceMenuId.value =
        openSpaceMenuId.value === space.id ? null : space.id;
}

function closeSpaceMenu(): void {
    openSpaceMenuId.value = null;
}

function openEditSpace(space: StudySpace, event?: MouseEvent): void {
    event?.stopPropagation();

    editingSpace.value = space;
    editSpaceName.value = space.name;
    editSpaceDescription.value = space.description ?? "";
    editSpaceOpen.value = true;
    openSpaceMenuId.value = null;
    errorMessage.value = null;
}

function closeEditSpace(): void {
    editSpaceOpen.value = false;
    editingSpace.value = null;
    editSpaceName.value = "";
    editSpaceDescription.value = "";
    savingEditSpace.value = false;
}

async function updateSpaceRequest(
    id: number,
    payload: {
        name: string;
        description?: string | null;
        cover?: string | null;
        color?: string | null;
    },
): Promise<StudySpace> {
    const response = await apiFetch<{ status: string; space: StudySpace }>(
        `/api/spaces/${id}`,
        {
            method: "PATCH",
            body: JSON.stringify(payload),
        },
    );

    return response.space;
}

async function deleteSpaceRequest(id: number): Promise<void> {
    await apiFetch<{ status: string }>(`/api/spaces/${id}`, {
        method: "DELETE",
    });
}

async function handleUpdateSpace(): Promise<void> {
    if (!editingSpace.value || savingEditSpace.value) return;

    const name = editSpaceName.value.trim();
    const description = editSpaceDescription.value.trim();

    if (!name) {
        errorMessage.value = "O Espaço precisa de ter nome.";
        return;
    }

    savingEditSpace.value = true;
    errorMessage.value = null;

    try {
        const updated = await updateSpaceRequest(editingSpace.value.id, {
            name,
            description: description || null,
            cover: editingSpace.value.cover ?? null,
            color: editingSpace.value.color ?? "#009957",
        });

        spaces.value = spaces.value.map((space) =>
            space.id === updated.id ? updated : space,
        );

        closeEditSpace();
    } catch (error) {
        console.error("[TUTS] Falha ao editar espaço.", error);
        errorMessage.value =
            error instanceof Error
                ? error.message
                : "Não consegui editar o Espaço.";
    } finally {
        savingEditSpace.value = false;
    }
}

function openDeleteSpace(space: StudySpace, event?: MouseEvent): void {
    event?.stopPropagation();

    spaceToDelete.value = space;
    deleteSpaceOpen.value = true;
    openSpaceMenuId.value = null;
    errorMessage.value = null;
}

function closeDeleteSpace(): void {
    deleteSpaceOpen.value = false;
    spaceToDelete.value = null;
    deletingSpace.value = false;
}

async function handleDeleteSpace(): Promise<void> {
    if (!spaceToDelete.value || deletingSpace.value) return;

    deletingSpace.value = true;
    errorMessage.value = null;

    const targetId = spaceToDelete.value.id;

    try {
        await deleteSpaceRequest(targetId);

        spaces.value = spaces.value.filter((space) => space.id !== targetId);

        closeDeleteSpace();
    } catch (error) {
        console.error("[TUTS] Falha ao apagar espaço.", error);
        errorMessage.value =
            error instanceof Error
                ? error.message
                : "Não consegui apagar o Espaço.";
    } finally {
        deletingSpace.value = false;
    }
}

async function loadData(): Promise<void> {
    loading.value = true;
    errorMessage.value = null;

    try {
        const [subjects, spacesResponse, chatResponse] = await Promise.all([
            fetchMySubjects(),
            fetchSpaces().catch(() => []),
            apiFetch<{ status: string; chats: ChatSummary[] }>(
                "/api/chat/ucs",
            ).catch(() => ({
                status: "erro",
                chats: [],
            })),
        ]);

        ucs.value = subjects as UCWithAcademicMeta[];
        spaces.value = spacesResponse;
        chats.value = chatResponse.chats ?? [];
    } catch (error) {
        console.error("[TUTS] Falha ao carregar UC/Espaços.", error);
        errorMessage.value = "Não foi possível carregar os dados.";
    } finally {
        loading.value = false;
    }
}

async function handleCreateSpace(): Promise<void> {
    const name = spaceName.value.trim();
    const description = spaceDescription.value.trim();

    if (!name || savingSpace.value) return;

    if (!canCreateSpace.value) {
        errorMessage.value =
            "Atingiste o limite de 5 Espaços. Elimina um Espaço existente para criar outro.";
        createSpaceOpen.value = false;
        return;
    }

    savingSpace.value = true;
    errorMessage.value = null;

    try {
        const space = await createSpace({
            name,
            description: description || null,
            color: "#009957",
        });

        spaces.value = [space, ...spaces.value];
        spaceName.value = "";
        spaceDescription.value = "";
        createSpaceOpen.value = false;
        activeTab.value = "spaces";

        await router.push({
            name: "space-detail",
            params: { id: String(space.id) },
        });
    } catch (error) {
        console.error("[TUTS] Falha ao criar espaço.", error);

        errorMessage.value =
            error instanceof Error
                ? error.message
                : "Não consegui criar o Espaço. Confirma se preencheste o nome.";
    } finally {
        savingSpace.value = false;
    }
}

function openChat(chat: ChatSummary): void {
    const query: Record<string, string> = {
        chat_id: String(chat.chat_id),
    };

    if (chat.context_type === "space" && chat.space_id) {
        query.context = "space";
        query.space_id = String(chat.space_id);

        if (chat.nome_espaco) {
            query.space = chat.nome_espaco;
        }
    } else if (chat.nome_uc) {
        query.context = "uc";
        query.uc = chat.nome_uc;
    }

    router.push({
        name: "chat",
        query,
    });
}

function openSpace(space: StudySpace): void {
    router.push({
        name: "space-detail",
        params: { id: String(space.id) },
    });
}

function formatDate(date: string | null): string {
    if (!date) return "Sem data";

    try {
        return new Date(date).toLocaleString("pt-PT", {
            day: "2-digit",
            month: "short",
            hour: "2-digit",
            minute: "2-digit",
        });
    } catch {
        return "Sem data";
    }
}

onMounted(loadData);
</script>

<template>
    <div
        style="
            height: 100%;
            overflow-y: auto;
            background: #ffffff;
            padding-bottom: 40px;
        "
        @click="closeSpaceMenu"
    >
        <div style="max-width: 1200px; margin: 0 auto; padding: 32px 24px">
            <div
                style="
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 16px;
                    margin-bottom: 28px;
                "
            >
                <div>
                    <h1
                        style="
                            font-family: Inter, sans-serif;
                            font-weight: 700;
                            font-size: 28px;
                            color: #1e1e1e;
                            margin: 0 0 6px;
                        "
                    >
                        UC's & Espaços
                    </h1>

                    <p
                        style="
                            font-family: Inter, sans-serif;
                            font-size: 13px;
                            color: #9e9e9e;
                            margin: 0;
                        "
                    >
                        Organiza unidades curriculares, espaços de estudo e
                        conversas temporárias.
                    </p>
                </div>

                <button
                    :disabled="activeTab === 'spaces' && !canCreateSpace"
                    :style="{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '8px',
                        border: 'none',
                        borderRadius: '12px',
                        background:
                            activeTab === 'spaces' && !canCreateSpace
                                ? '#BDBABA'
                                : '#009957',
                        color: '#ffffff',
                        fontFamily: 'Inter, sans-serif',
                        fontWeight: 700,
                        fontSize: '13px',
                        padding: '11px 15px',
                        cursor:
                            activeTab === 'spaces' && !canCreateSpace
                                ? 'not-allowed'
                                : 'pointer',
                        opacity:
                            activeTab === 'spaces' && !canCreateSpace ? 0.7 : 1,
                    }"
                    @click.stop="openCreateSpaceModal"
                >
                    <FolderPlus :size="16" color="#ffffff" />
                    Criar Espaço
                </button>
            </div>

            <div
                style="
                    display: flex;
                    gap: 12px;
                    margin-bottom: 24px;
                    border-bottom: 1px solid #e5e5e5;
                "
            >
                <button
                    v-for="tab in [
                        { id: 'ucs', label: 'UCs' },
                        { id: 'spaces', label: 'Espaços' },
                        { id: 'conversations', label: 'Conversas' },
                    ]"
                    :key="tab.id"
                    :style="{
                        background: 'none',
                        border: 'none',
                        borderBottom:
                            activeTab === tab.id
                                ? '2px solid #009957'
                                : '2px solid transparent',
                        color: activeTab === tab.id ? '#009957' : '#9E9E9E',
                        fontFamily: 'Inter, sans-serif',
                        fontWeight: activeTab === tab.id ? 700 : 500,
                        fontSize: '14px',
                        padding: '0 0 12px',
                        marginRight: '20px',
                        cursor: 'pointer',
                    }"
                    @click.stop="changeTab(tab.id as TabType)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div style="position: relative; margin-bottom: 18px">
                <Search
                    :size="16"
                    color="#9E9E9E"
                    style="
                        position: absolute;
                        left: 14px;
                        top: 50%;
                        transform: translateY(-50%);
                    "
                />

                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="
                        activeTab === 'spaces'
                            ? 'Pesquisar Espaços...'
                            : activeTab === 'ucs'
                              ? 'Pesquisar UCs...'
                              : 'Pesquisar conversas...'
                    "
                    style="
                        width: 100%;
                        box-sizing: border-box;
                        border: 1px solid #e5e5e5;
                        border-radius: 12px;
                        padding: 12px 14px 12px 40px;
                        font-family: Inter, sans-serif;
                        outline: none;
                    "
                    @click.stop
                />
            </div>

            <div
                v-if="activeTab === 'spaces'"
                style="
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 16px;
                    margin: 0 0 22px;
                    padding: 12px 14px;
                    border-radius: 12px;
                    background: #f6f8f7;
                    border: 1px solid #e5e5e5;
                "
            >
                <div>
                    <p
                        style="
                            font-family: Inter, sans-serif;
                            font-weight: 800;
                            font-size: 13px;
                            color: #1e1e1e;
                            margin: 0 0 2px;
                        "
                    >
                        Limite de Espaços
                    </p>

                    <p
                        style="
                            font-family: Inter, sans-serif;
                            font-weight: 400;
                            font-size: 12px;
                            color: #656966;
                            margin: 0;
                        "
                    >
                        Podes criar até 5 Espaços. Estás a usar
                        {{ spacesCount }}/{{ MAX_SPACES_PER_USER }}.
                    </p>
                </div>

                <span
                    :style="{
                        fontFamily: 'Inter, sans-serif',
                        fontWeight: 800,
                        fontSize: '12px',
                        color: canCreateSpace ? '#009957' : '#E53E3E',
                        background: canCreateSpace
                            ? 'rgba(0,153,87,0.10)'
                            : 'rgba(229,62,62,0.10)',
                        borderRadius: '999px',
                        padding: '6px 10px',
                        whiteSpace: 'nowrap',
                    }"
                >
                    {{ spacesCount }}/{{ MAX_SPACES_PER_USER }}
                </span>
            </div>

            <p
                v-if="errorMessage"
                style="
                    font-family: Inter, sans-serif;
                    color: #e53e3e;
                    margin-bottom: 16px;
                "
            >
                {{ errorMessage }}
            </p>

            <p
                v-if="loading"
                style="font-family: Inter, sans-serif; color: #9e9e9e"
            >
                A carregar dados...
            </p>

            <template v-else>
                <div
                    v-if="activeTab === 'ucs'"
                    style="display: flex; flex-direction: column; gap: 34px"
                >
                    <p
                        v-if="filteredUcs.length === 0"
                        style="
                            font-family: Inter, sans-serif;
                            color: #9e9e9e;
                            margin: 0;
                        "
                    >
                        Não encontrei UCs com essa pesquisa.
                    </p>

                    <section
                        v-for="yearGroup in groupedUcs"
                        :key="yearGroup.year"
                        class="academic-year-section"
                    >
                        <div class="academic-year-header">
                            <div>
                                <h2 class="academic-year-title">
                                    {{ yearGroup.year }}
                                </h2>

                                <p class="academic-year-subtitle">
                                    {{ countYearSubjects(yearGroup) }} unidades
                                    curriculares
                                </p>
                            </div>
                        </div>

                        <div
                            v-for="semesterGroup in yearGroup.semesters"
                            :key="`${yearGroup.year}-${semesterGroup.semester}`"
                            class="academic-semester-block"
                        >
                            <div class="academic-semester-header">
                                <span class="academic-semester-dot" />
                                <h3 class="academic-semester-title">
                                    {{ semesterGroup.semester }}
                                </h3>
                            </div>

                            <div class="uc-card-grid">
                                <div
                                    v-for="uc in semesterGroup.items"
                                    :key="uc.id"
                                    class="uc-card-equal"
                                >
                                    <UCCard v-bind="uc" />
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div v-else-if="activeTab === 'spaces'" class="space-card-grid">
                    <div
                        v-for="space in filteredSpaces"
                        :key="space.id"
                        class="space-card"
                        role="button"
                        tabindex="0"
                        @click.stop="openSpace(space)"
                        @keydown.enter.prevent="openSpace(space)"
                    >
                        <div
                            :style="{
                                height: '118px',
                                background:
                                    space.cover ||
                                    `linear-gradient(135deg, ${space.color || '#009957'} 0%, #1E1E1E 100%)`,
                                position: 'relative',
                                padding: '14px',
                                boxSizing: 'border-box',
                                display: 'flex',
                                alignItems: 'flex-end',
                                flexShrink: 0,
                            }"
                        >
                            <div
                                style="
                                    position: absolute;
                                    inset: 0;
                                    background: rgba(0, 0, 0, 0.22);
                                "
                            />

                            <span
                                style="
                                    position: relative;
                                    z-index: 1;
                                    font-family: Inter, sans-serif;
                                    font-size: 11px;
                                    font-weight: 800;
                                    color: #ffffff;
                                    letter-spacing: 0.08em;
                                    text-transform: uppercase;
                                "
                            >
                                Espaço
                            </span>

                            <button
                                type="button"
                                class="space-menu-button"
                                @click="toggleSpaceMenu(space, $event)"
                            >
                                <MoreVertical :size="16" color="#ffffff" />
                            </button>

                            <div
                                v-if="openSpaceMenuId === space.id"
                                class="space-menu"
                                @click.stop
                            >
                                <button
                                    type="button"
                                    class="space-menu-item"
                                    @click="openEditSpace(space, $event)"
                                >
                                    Editar Espaço
                                </button>

                                <button
                                    type="button"
                                    class="space-menu-item danger"
                                    @click="openDeleteSpace(space, $event)"
                                >
                                    Apagar Espaço
                                </button>
                            </div>
                        </div>

                        <div
                            style="
                                padding: 16px;
                                display: flex;
                                flex-direction: column;
                                flex: 1;
                            "
                        >
                            <p
                                style="
                                    font-family: Inter, sans-serif;
                                    font-weight: 800;
                                    font-size: 15px;
                                    color: #1e1e1e;
                                    margin: 0 0 6px;
                                "
                            >
                                {{ space.name }}
                            </p>

                            <p
                                style="
                                    font-family: Inter, sans-serif;
                                    font-weight: 400;
                                    font-size: 12px;
                                    color: #656966;
                                    margin: 0 0 12px;
                                    line-height: 1.45;
                                    flex: 1;
                                "
                            >
                                {{
                                    space.description ||
                                    "Espaço livre para conversas, materiais pessoais e organização temática."
                                }}
                            </p>

                            <p
                                style="
                                    font-family: Inter, sans-serif;
                                    font-size: 11px;
                                    color: #bdbaba;
                                    margin: 0;
                                "
                            >
                                {{ space.chats_count ?? 0 }} conversas ·
                                Atualizado {{ formatDate(space.updated_at) }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="filteredSpaces.length === 0"
                        style="
                            grid-column: 1 / -1;
                            border: 1px dashed #e5e5e5;
                            border-radius: 16px;
                            padding: 32px;
                            text-align: center;
                        "
                    >
                        <FolderPlus :size="28" color="#BDBABA" />

                        <p
                            style="
                                font-family: Inter, sans-serif;
                                color: #656966;
                                margin-bottom: 4px;
                            "
                        >
                            Ainda não tens Espaços.
                        </p>

                        <p
                            style="
                                font-family: Inter, sans-serif;
                                color: #bdbaba;
                                font-size: 13px;
                                margin: 0 0 16px;
                            "
                        >
                            Cria um espaço para projetos, preparação de testes
                            ou estudo transversal.
                        </p>

                        <button
                            :disabled="!canCreateSpace"
                            :style="{
                                border: 'none',
                                borderRadius: '10px',
                                background: canCreateSpace
                                    ? '#009957'
                                    : '#BDBABA',
                                color: '#ffffff',
                                fontFamily: 'Inter, sans-serif',
                                fontWeight: 700,
                                fontSize: '13px',
                                padding: '10px 14px',
                                cursor: canCreateSpace
                                    ? 'pointer'
                                    : 'not-allowed',
                                opacity: canCreateSpace ? 1 : 0.7,
                            }"
                            @click.stop="openCreateSpaceModal"
                        >
                            Criar primeiro Espaço
                        </button>
                    </div>
                </div>

                <div
                    v-else
                    style="display: flex; flex-direction: column; gap: 12px"
                >
                    <p
                        v-if="filteredChats.length === 0"
                        style="font-family: Inter, sans-serif; color: #9e9e9e"
                    >
                        Ainda não tens conversas guardadas.
                    </p>

                    <button
                        v-for="chat in filteredChats"
                        :key="chat.chat_id"
                        style="
                            display: flex;
                            align-items: center;
                            gap: 14px;
                            background: #ffffff;
                            border: 1px solid #e5e5e5;
                            border-radius: 14px;
                            padding: 16px;
                            cursor: pointer;
                            text-align: left;
                        "
                        @click="openChat(chat)"
                    >
                        <div
                            style="
                                width: 40px;
                                height: 40px;
                                border-radius: 10px;
                                background: #f5f5f5;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            "
                        >
                            <MessageCircle :size="18" color="#009957" />
                        </div>

                        <div style="flex: 1">
                            <p
                                style="
                                    font-family: Inter, sans-serif;
                                    font-weight: 700;
                                    font-size: 14px;
                                    color: #1e1e1e;
                                    margin: 0 0 4px;
                                "
                            >
                                {{ chat.title || "Conversa sem título" }}
                            </p>

                            <p
                                style="
                                    font-family: Inter, sans-serif;
                                    font-size: 12px;
                                    color: #9e9e9e;
                                    margin: 0;
                                "
                            >
                                {{
                                    chat.nome_espaco ||
                                    chat.nome_uc ||
                                    "Conversa temporária"
                                }}
                                · {{ formatDate(chat.updated_at) }}
                            </p>
                        </div>

                        <MoreVertical :size="16" color="#BDBABA" />
                    </button>
                </div>
            </template>
        </div>

        <div
            v-if="createSpaceOpen"
            style="
                position: fixed;
                inset: 0;
                z-index: 90;
                background: rgba(0, 0, 0, 0.28);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            "
            @click.self="createSpaceOpen = false"
        >
            <div
                style="
                    width: 100%;
                    max-width: 460px;
                    background: #ffffff;
                    border-radius: 18px;
                    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.22);
                    overflow: hidden;
                "
            >
                <div
                    style="
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        padding: 18px 20px;
                        border-bottom: 1px solid #f0f0f0;
                    "
                >
                    <div>
                        <p
                            style="
                                font-family: Inter, sans-serif;
                                font-weight: 800;
                                font-size: 16px;
                                color: #1e1e1e;
                                margin: 0 0 3px;
                            "
                        >
                            Criar Espaço
                        </p>

                        <p
                            style="
                                font-family: Inter, sans-serif;
                                font-size: 12px;
                                color: #9e9e9e;
                                margin: 0;
                            "
                        >
                            Para estudo transversal, projetos ou preparação de
                            testes.
                        </p>
                    </div>

                    <button
                        style="border: none; background: none; cursor: pointer"
                        @click="createSpaceOpen = false"
                    >
                        <X :size="18" color="#656966" />
                    </button>
                </div>

                <div
                    style="
                        padding: 20px;
                        display: flex;
                        flex-direction: column;
                        gap: 14px;
                    "
                >
                    <div
                        style="
                            padding: 11px 12px;
                            border-radius: 12px;
                            background: #f6f8f7;
                            border: 1px solid #e5e5e5;
                        "
                    >
                        <p
                            style="
                                font-family: Inter, sans-serif;
                                font-size: 12px;
                                font-weight: 700;
                                color: #1e1e1e;
                                margin: 0 0 2px;
                            "
                        >
                            Limite de Espaços
                        </p>

                        <p
                            style="
                                font-family: Inter, sans-serif;
                                font-size: 12px;
                                color: #656966;
                                margin: 0;
                            "
                        >
                            Estás a usar {{ spacesCount }}/{{
                                MAX_SPACES_PER_USER
                            }}
                            Espaços.
                        </p>
                    </div>

                    <label
                        style="
                            font-family: Inter, sans-serif;
                            font-size: 12px;
                            font-weight: 700;
                            color: #1e1e1e;
                        "
                    >
                        Nome do Espaço

                        <input
                            v-model="spaceName"
                            type="text"
                            placeholder="Ex: Exames 2026"
                            style="
                                margin-top: 7px;
                                width: 100%;
                                box-sizing: border-box;
                                border: 1px solid #e5e5e5;
                                border-radius: 11px;
                                padding: 11px 12px;
                                font-family: Inter, sans-serif;
                                outline: none;
                            "
                            @keydown.enter.prevent="handleCreateSpace"
                        />
                    </label>

                    <label
                        style="
                            font-family: Inter, sans-serif;
                            font-size: 12px;
                            font-weight: 700;
                            color: #1e1e1e;
                        "
                    >
                        Descrição opcional

                        <textarea
                            v-model="spaceDescription"
                            placeholder="Ex: Fichas, resumos e dúvidas para preparar exames."
                            style="
                                margin-top: 7px;
                                width: 100%;
                                min-height: 92px;
                                box-sizing: border-box;
                                border: 1px solid #e5e5e5;
                                border-radius: 11px;
                                padding: 11px 12px;
                                font-family: Inter, sans-serif;
                                outline: none;
                                resize: vertical;
                            "
                        />
                    </label>
                </div>

                <div
                    style="
                        display: flex;
                        justify-content: flex-end;
                        gap: 10px;
                        padding: 14px 20px 20px;
                    "
                >
                    <button
                        style="
                            border: 1px solid #e5e5e5;
                            background: #ffffff;
                            color: #656966;
                            border-radius: 10px;
                            padding: 10px 14px;
                            font-family: Inter, sans-serif;
                            font-weight: 700;
                            cursor: pointer;
                        "
                        @click="createSpaceOpen = false"
                    >
                        Cancelar
                    </button>

                    <button
                        :disabled="
                            !spaceName.trim() || savingSpace || !canCreateSpace
                        "
                        :style="{
                            border: 'none',
                            background: '#009957',
                            color: '#ffffff',
                            borderRadius: '10px',
                            padding: '10px 14px',
                            fontFamily: 'Inter, sans-serif',
                            fontWeight: 800,
                            cursor:
                                spaceName.trim() &&
                                !savingSpace &&
                                canCreateSpace
                                    ? 'pointer'
                                    : 'not-allowed',
                            opacity:
                                spaceName.trim() &&
                                !savingSpace &&
                                canCreateSpace
                                    ? 1
                                    : 0.55,
                        }"
                        @click="handleCreateSpace"
                    >
                        {{ savingSpace ? "A criar..." : "Criar Espaço" }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="editSpaceOpen"
            style="
                position: fixed;
                inset: 0;
                z-index: 95;
                background: rgba(0, 0, 0, 0.28);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            "
            @click.self="closeEditSpace"
        >
            <div
                style="
                    width: 100%;
                    max-width: 460px;
                    background: #ffffff;
                    border-radius: 18px;
                    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.22);
                    overflow: hidden;
                "
            >
                <div
                    style="
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        padding: 18px 20px;
                        border-bottom: 1px solid #f0f0f0;
                    "
                >
                    <div>
                        <p
                            style="
                                font-family: Inter, sans-serif;
                                font-weight: 800;
                                font-size: 16px;
                                color: #1e1e1e;
                                margin: 0 0 3px;
                            "
                        >
                            Editar Espaço
                        </p>

                        <p
                            style="
                                font-family: Inter, sans-serif;
                                font-size: 12px;
                                color: #9e9e9e;
                                margin: 0;
                            "
                        >
                            Altera o nome ou a descrição deste Espaço.
                        </p>
                    </div>

                    <button
                        style="border: none; background: none; cursor: pointer"
                        @click="closeEditSpace"
                    >
                        <X :size="18" color="#656966" />
                    </button>
                </div>

                <div
                    style="
                        padding: 20px;
                        display: flex;
                        flex-direction: column;
                        gap: 14px;
                    "
                >
                    <label
                        style="
                            font-family: Inter, sans-serif;
                            font-size: 12px;
                            font-weight: 700;
                            color: #1e1e1e;
                        "
                    >
                        Nome do Espaço

                        <input
                            v-model="editSpaceName"
                            type="text"
                            placeholder="Nome do Espaço"
                            style="
                                margin-top: 7px;
                                width: 100%;
                                box-sizing: border-box;
                                border: 1px solid #e5e5e5;
                                border-radius: 11px;
                                padding: 11px 12px;
                                font-family: Inter, sans-serif;
                                outline: none;
                            "
                            @keydown.enter.prevent="handleUpdateSpace"
                        />
                    </label>

                    <label
                        style="
                            font-family: Inter, sans-serif;
                            font-size: 12px;
                            font-weight: 700;
                            color: #1e1e1e;
                        "
                    >
                        Descrição

                        <textarea
                            v-model="editSpaceDescription"
                            placeholder="Descrição do Espaço"
                            style="
                                margin-top: 7px;
                                width: 100%;
                                min-height: 92px;
                                box-sizing: border-box;
                                border: 1px solid #e5e5e5;
                                border-radius: 11px;
                                padding: 11px 12px;
                                font-family: Inter, sans-serif;
                                outline: none;
                                resize: vertical;
                            "
                        />
                    </label>
                </div>

                <div
                    style="
                        display: flex;
                        justify-content: flex-end;
                        gap: 10px;
                        padding: 14px 20px 20px;
                    "
                >
                    <button
                        style="
                            border: 1px solid #e5e5e5;
                            background: #ffffff;
                            color: #656966;
                            border-radius: 10px;
                            padding: 10px 14px;
                            font-family: Inter, sans-serif;
                            font-weight: 700;
                            cursor: pointer;
                        "
                        @click="closeEditSpace"
                    >
                        Cancelar
                    </button>

                    <button
                        :disabled="!editSpaceName.trim() || savingEditSpace"
                        :style="{
                            border: 'none',
                            background: '#009957',
                            color: '#ffffff',
                            borderRadius: '10px',
                            padding: '10px 14px',
                            fontFamily: 'Inter, sans-serif',
                            fontWeight: 800,
                            cursor:
                                editSpaceName.trim() && !savingEditSpace
                                    ? 'pointer'
                                    : 'not-allowed',
                            opacity:
                                editSpaceName.trim() && !savingEditSpace
                                    ? 1
                                    : 0.55,
                        }"
                        @click="handleUpdateSpace"
                    >
                        {{
                            savingEditSpace
                                ? "A guardar..."
                                : "Guardar alterações"
                        }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="deleteSpaceOpen"
            style="
                position: fixed;
                inset: 0;
                z-index: 96;
                background: rgba(0, 0, 0, 0.34);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            "
            @click.self="closeDeleteSpace"
        >
            <div
                style="
                    width: 100%;
                    max-width: 430px;
                    background: #ffffff;
                    border-radius: 18px;
                    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.22);
                    overflow: hidden;
                "
            >
                <div style="padding: 20px">
                    <p
                        style="
                            font-family: Inter, sans-serif;
                            font-weight: 900;
                            font-size: 17px;
                            color: #1e1e1e;
                            margin: 0 0 8px;
                        "
                    >
                        Apagar Espaço?
                    </p>

                    <p
                        style="
                            font-family: Inter, sans-serif;
                            font-size: 13px;
                            color: #656966;
                            line-height: 1.5;
                            margin: 0;
                        "
                    >
                        Vais apagar o Espaço
                        <strong>{{ spaceToDelete?.name }}</strong
                        >. Esta ação não pode ser desfeita.
                    </p>
                </div>

                <div
                    style="
                        display: flex;
                        justify-content: flex-end;
                        gap: 10px;
                        padding: 0 20px 20px;
                    "
                >
                    <button
                        style="
                            border: 1px solid #e5e5e5;
                            background: #ffffff;
                            color: #656966;
                            border-radius: 10px;
                            padding: 10px 14px;
                            font-family: Inter, sans-serif;
                            font-weight: 700;
                            cursor: pointer;
                        "
                        @click="closeDeleteSpace"
                    >
                        Cancelar
                    </button>

                    <button
                        :disabled="deletingSpace"
                        :style="{
                            border: 'none',
                            background: '#E53E3E',
                            color: '#ffffff',
                            borderRadius: '10px',
                            padding: '10px 14px',
                            fontFamily: 'Inter, sans-serif',
                            fontWeight: 800,
                            cursor: deletingSpace ? 'not-allowed' : 'pointer',
                            opacity: deletingSpace ? 0.6 : 1,
                        }"
                        @click="handleDeleteSpace"
                    >
                        {{ deletingSpace ? "A apagar..." : "Apagar Espaço" }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.academic-year-section {
    display: flex;
    flex-direction: column;
    gap: 22px;
}

.academic-year-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e5e5e5;
}

.academic-year-title {
    font-family: Inter, sans-serif;
    font-weight: 900;
    font-size: 22px;
    color: #1e1e1e;
    margin: 0 0 4px;
}

.academic-year-subtitle {
    font-family: Inter, sans-serif;
    font-weight: 500;
    font-size: 12px;
    color: #9e9e9e;
    margin: 0;
}

.academic-semester-block {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.academic-semester-header {
    display: flex;
    align-items: center;
    gap: 9px;
}

.academic-semester-dot {
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: #009957;
    box-shadow: 0 0 0 5px rgba(0, 153, 87, 0.1);
}

.academic-semester-title {
    font-family: Inter, sans-serif;
    font-weight: 800;
    font-size: 14px;
    color: #1e1e1e;
    margin: 0;
}

.uc-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    align-items: stretch;
    column-gap: 20px;
    row-gap: 20px;
}

.uc-card-equal {
    height: 240px;
    display: flex;
    min-width: 0;
}

.uc-card-equal :deep(*) {
    box-sizing: border-box;
}

.uc-card-equal :deep(> *) {
    width: 100%;
    height: 100%;
    min-height: 0;
    flex: 1;
}

.space-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    align-items: stretch;
    gap: 20px;
}

.space-card {
    min-height: 255px;
    height: 100%;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border: 1px solid #e5e5e5;
    border-radius: 16px;
    overflow: visible;
    cursor: pointer;
    padding: 0;
    text-align: left;
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.04);
    position: relative;
}

.space-card > div:first-child {
    border-radius: 16px 16px 0 0;
    overflow: hidden;
}

.space-card > div:last-child {
    border-radius: 0 0 16px 16px;
    background: #ffffff;
}

.space-menu-button {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 4;
    width: 30px;
    height: 30px;
    border: none;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.16);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.space-menu-button:hover {
    background: rgba(255, 255, 255, 0.26);
}

.space-menu {
    position: absolute;
    top: 44px;
    right: 10px;
    z-index: 20;
    width: 160px;
    padding: 6px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #e5e5e5;
    box-shadow: 0 14px 35px rgba(0, 0, 0, 0.16);
}

.space-menu-item {
    width: 100%;
    border: none;
    background: transparent;
    border-radius: 9px;
    padding: 9px 10px;
    text-align: left;
    font-family: Inter, sans-serif;
    font-size: 12px;
    font-weight: 700;
    color: #1e1e1e;
    cursor: pointer;
}

.space-menu-item:hover {
    background: #f6f8f7;
}

.space-menu-item.danger {
    color: #e53e3e;
}

.space-menu-item.danger:hover {
    background: rgba(229, 62, 62, 0.08);
}
</style>
