import { createAppConfig } from '@nextcloud/vite-config'
import path from 'path'

// https://vite.dev/config/
export default createAppConfig(
  {
    main: path.resolve(path.join('src', 'main.ts')),
  },
  {
    config: {
      root: 'src',
      build: {
        outDir: process.cwd(),
        cssCodeSplit: false,
        rollupOptions: {
          output: {
            manualChunks: {
              vendor: ['vue', 'vue-router'],
            },
          },
        },
      },
    },
  },
)
