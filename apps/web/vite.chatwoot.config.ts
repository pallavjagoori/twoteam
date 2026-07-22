import yaml from '@rollup/plugin-yaml';
import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import postcssImport from 'postcss-import';
import postcssPresetEnv from 'postcss-preset-env';
import tailwindcss from 'tailwindcss';
import loadTailwindConfig from 'tailwindcss/loadConfig.js';
import { defineConfig } from 'vite';

const webRoot = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = path.resolve(webRoot, '../..');
const chatwootRoot = path.resolve(repositoryRoot, 'upstream/chatwoot');
const javascriptRoot = path.resolve(chatwootRoot, 'app/javascript');
const runtimeAdapter = path.resolve(webRoot, 'src/adapters/chatwootRuntime.ts');
const actionCableAdapter = path.resolve(webRoot, 'src/adapters/actionCable.ts');
const entrypoint = (name: string) =>
  path.resolve(javascriptRoot, `entrypoints/${name}.js`);
const tailwindConfig = loadTailwindConfig(
  path.resolve(chatwootRoot, 'tailwind.config.js'),
);
const tailwindContent = Array.isArray(tailwindConfig.content)
  ? tailwindConfig.content
  : tailwindConfig.content.files;

tailwindConfig.content = tailwindContent.map(pattern =>
  path.resolve(chatwootRoot, pattern),
);

const aliases = [
  {
    find: /^@rails\/actioncable(?:\/src)?$/,
    replacement: actionCableAdapter,
  },
  { find: 'vue', replacement: 'vue/dist/vue.runtime.esm-bundler.js' },
  {
    find: 'components',
    replacement: path.resolve(javascriptRoot, 'dashboard/components'),
  },
  {
    find: 'next',
    replacement: path.resolve(javascriptRoot, 'dashboard/components-next'),
  },
  { find: 'v3', replacement: path.resolve(javascriptRoot, 'v3') },
  {
    find: 'dashboard',
    replacement: path.resolve(javascriptRoot, 'dashboard'),
  },
  {
    find: 'helpers',
    replacement: path.resolve(javascriptRoot, 'shared/helpers'),
  },
  {
    find: 'shared',
    replacement: path.resolve(javascriptRoot, 'shared'),
  },
  {
    find: 'survey',
    replacement: path.resolve(javascriptRoot, 'survey'),
  },
  {
    find: 'widget',
    replacement: path.resolve(javascriptRoot, 'widget'),
  },
  {
    find: 'assets',
    replacement: path.resolve(javascriptRoot, 'dashboard/assets'),
  },
];

const railsReplacementAdapter = () => ({
  name: 'twoteam-rails-replacement-adapter',
  enforce: 'pre' as const,
  transform(code: string, id: string) {
    if (!id.startsWith(`${javascriptRoot}${path.sep}entrypoints${path.sep}`)) {
      return null;
    }

    return {
      code: `import ${JSON.stringify(runtimeAdapter)};\n${code}`,
      map: null,
    };
  },
});

export default defineConfig({
  base: process.env.TWOTEAM_ASSET_BASE_URL ?? '/',
  root: chatwootRoot,
  publicDir: false,
  plugins: [
    railsReplacementAdapter(),
    vue({
      template: {
        compilerOptions: {
          isCustomElement: tag => tag === 'ninja-keys',
        },
      },
    }),
    yaml(),
  ],
  resolve: {
    alias: aliases,
  },
  css: {
    postcss: {
      plugins: [
        postcssPresetEnv({
          autoprefixer: {
            flexbox: 'no-2009',
          },
          stage: 3,
        }),
        postcssImport(),
        tailwindcss(tailwindConfig),
        autoprefixer(),
      ],
    },
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler',
      },
    },
  },
  build: {
    emptyOutDir: true,
    manifest: true,
    outDir: path.resolve(webRoot, 'dist/chatwoot'),
    rollupOptions: {
      input: {
        dashboard: entrypoint('dashboard'),
        v3app: entrypoint('v3app'),
        portal: entrypoint('portal'),
        widget: entrypoint('widget'),
        sdk: entrypoint('sdk'),
        superadmin: entrypoint('superadmin'),
        survey: entrypoint('survey'),
        superadmin_pages: entrypoint('superadmin_pages'),
      },
      output: {
        banner: 'globalThis.regeneratorRuntime ??= {};',
        entryFileNames: 'assets/[name].js',
      },
    },
  },
});
