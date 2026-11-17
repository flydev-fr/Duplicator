import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Builds the admin app into ../Assets/app/ with fixed names, loaded by ProcessDuplicator::renderApp().
// One self-running script (IIFE) and one stylesheet: ProcessWire adds them with plain <script>/<link> tags.
export default defineConfig({
  plugins: [react()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    outDir: '../Assets/app',
    emptyOutDir: true,
    sourcemap: false,
    lib: {
      entry: 'src/main.tsx',
      name: 'DuplicatorApp',
      formats: ['iife'],
      fileName: () => 'duplicator-app.js',
      cssFileName: 'duplicator-app',
    },
  },
});
