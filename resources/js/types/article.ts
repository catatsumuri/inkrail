export type Article = {
    edit_version?: number;
    translationStatus?: Record<string, TranslationState>;
    id: number;
    resolvedLocale?: string;
    availableLocales?: string[];
    translations?: Record<string, { title: string; content: string }>;
    title: string;
    content: string;
    slug: string;
    is_published: boolean;
};

export type TranslationState = {
    state: 'missing' | 'deleted' | 'unreviewed' | 'reviewed' | 'other_changed';
    revisionId: number | null;
    baselineId: number | null;
};
