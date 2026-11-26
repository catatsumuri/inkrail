export type Article = {
    is_sandbox?: boolean;
    edit_version?: number;
    topics?: string[];
    eyecatch?: string | null;
    eyecatch_url?: string | null;
    translationStatus?: Record<string, TranslationState>;
    id: number;
    resolvedLocale?: string;
    availableLocales?: string[];
    translations?: Record<string, { title: string; content: string }>;
    title: string;
    content: string;
    slug: string;
    is_published: boolean;
    publication_status?: 'draft' | 'scheduled' | 'published';
    published_at?: string | null;
};

export type TranslationState = {
    state: 'missing' | 'deleted' | 'unreviewed' | 'reviewed' | 'other_changed';
    revisionId: number | null;
    baselineId: number | null;
};
