import { apiFetch } from "@/app/services/api";

export interface SubjectData {
    id: string;
    subject_id?: number | null;
    subjectId?: number | null;
    name: string;
    url?: string | null;
    teacher?: string | null;
    teacherNote?: string | null;
    year?: string | null;
    semester?: string | null;
    academicYear?: string | null;
    type?: "mandatory" | "elective" | string | null;
    electiveGroup?: string | null;
    cover?: string | null;
    shortCode?: string | null;
    description?: string | null;
}

interface MySubjectsResponse {
    status: string;
    subjects: SubjectData[];
    message?: string;
}

export async function fetchMySubjects(): Promise<SubjectData[]> {
    const response = await apiFetch<MySubjectsResponse>("/api/subjects");

    return (response.subjects ?? []).map((subject) => ({
        id: subject.id,
        subject_id: subject.subject_id ?? subject.subjectId ?? null,
        subjectId: subject.subjectId ?? subject.subject_id ?? null,
        name: subject.name,
        url: subject.url ?? null,
        teacher: subject.teacher ?? "Docente a definir",
        teacherNote: subject.teacherNote ?? null,
        year: subject.year ?? "Ano não definido",
        semester: subject.semester ?? "Semestre não definido",
        academicYear: subject.academicYear ?? "2025/2026",
        type: subject.type ?? "mandatory",
        electiveGroup: subject.electiveGroup ?? null,
        cover: subject.cover ?? null,
        shortCode: subject.shortCode ?? null,
        description:
            subject.description ?? `Unidade curricular de ${subject.name}.`,
    }));
}
