import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
  site: 'https://catatsumuri.github.io',
  base: '/laravel-react-secure-starter-kit',
  integrations: [
    starlight({
      title: 'Laravel React Secure Starter Kit',
      locales: {
        root: { label: '日本語', lang: 'ja' },
      },
      social: [
        {
          icon: 'github',
          label: 'GitHub',
          href: 'https://github.com/catatsumuri/laravel-react-secure-starter-kit',
        },
      ],
      sidebar: [
        { label: 'はじめに', items: [{ autogenerate: { directory: 'getting-started' } }] },
        { label: '開発ガイド', items: [{ autogenerate: { directory: 'guides' } }] },
        { label: 'セキュリティ', items: [{ autogenerate: { directory: 'security' } }] },
        { label: '運用', items: [{ autogenerate: { directory: 'operations' } }] },
      ],
    }),
  ],
});
