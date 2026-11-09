import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
  site: 'https://catatsumuri.github.io',
  base: '/laravel-react-secure-starter-kit',
  redirects: {
    '/': '/laravel-react-secure-starter-kit/about/',
    '/ja/': '/laravel-react-secure-starter-kit/ja/about/',
  },
  integrations: [
    starlight({
      title: 'Laravel React Secure Starter Kit',
      customCss: ['./src/styles/custom.css'],
      locales: {
        root: { label: 'English', lang: 'en' },
        ja: { label: '日本語', lang: 'ja' },
      },
      social: [
        {
          icon: 'github',
          label: 'GitHub',
          href: 'https://github.com/catatsumuri/laravel-react-secure-starter-kit',
        },
      ],
      sidebar: [
        { label: 'Start here', translations: { ja: 'はじめに' }, items: [
          { slug: 'about' },
        ] },
        { label: 'Installation', translations: { ja: '導入' }, items: [
          { autogenerate: { directory: 'getting-started' } },
        ] },
        { label: 'Features', translations: { ja: '機能' }, items: [
          { autogenerate: { directory: 'security' } },
          { autogenerate: { directory: 'sessions' } },
          { autogenerate: { directory: 'events' } },
          { autogenerate: { directory: 'notifications' } },
        ] },
        { label: 'Build & operate', translations: { ja: '開発・運用' }, items: [
          { slug: 'guides' },
          { autogenerate: { directory: 'operations' } },
        ] },
        { label: 'Appendix', translations: { ja: '付録' }, items: [
          { slug: 'guides/tests' },
          { slug: 'guides/access-blocking-checklist' },
        ] },
      ],
    }),
  ],
});
