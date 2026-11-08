import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
  site: 'https://catatsumuri.github.io',
  base: '/laravel-react-secure-starter-kit',
  integrations: [
    starlight({
      title: 'Laravel React Secure Starter Kit',
      customCss: ['./src/styles/custom.css'],
      locales: {
        root: { label: '日本語', lang: 'ja' },
        en: { label: 'English', lang: 'en' },
      },
      social: [
        {
          icon: 'github',
          label: 'GitHub',
          href: 'https://github.com/catatsumuri/laravel-react-secure-starter-kit',
        },
      ],
      sidebar: [
        { label: 'はじめに', translations: { en: 'Start here' }, items: [
          { slug: 'about' },
          { autogenerate: { directory: 'getting-started' } },
        ] },
        { label: '機能', translations: { en: 'Features' }, items: [
          { autogenerate: { directory: 'security' } },
          { autogenerate: { directory: 'sessions' } },
          { autogenerate: { directory: 'events' } },
          { autogenerate: { directory: 'notifications' } },
        ] },
        { label: '開発・運用', translations: { en: 'Build & operate' }, items: [
          { autogenerate: { directory: 'guides' } },
          { autogenerate: { directory: 'operations' } },
        ] },
      ],
    }),
  ],
});
