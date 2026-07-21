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

const aliases = {
  vue: 'vue/dist/vue.esm-bundler.js',
  components: path.resolve(javascriptRoot, 'dashboard/components'),
  next: path.resolve(javascriptRoot, 'dashboard/components-next'),
  v3: path.resolve(javascriptRoot, 'v3'),
  dashboard: path.resolve(javascriptRoot, 'dashboard'),
  helpers: path.resolve(javascriptRoot, 'shared/helpers'),
  shared: path.resolve(javascriptRoot, 'shared'),
  survey: path.resolve(javascriptRoot, 'survey'),
  widget: path.resolve(javascriptRoot, 'widget'),
  assets: path.resolve(javascriptRoot, 'dashboard/assets'),
};

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
        entryFileNames: 'assets/[name]-[hash].js',
      },
    },
  },
});
