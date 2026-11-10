export type Article = {
    id: number;
    resolvedLocale?: string;
    availableLocales?: string[];
    translations?: Record<string, { title: string; content: string }>;
    title: string;
    content: string;
    slug: string;
    is_published: boolean;
};
