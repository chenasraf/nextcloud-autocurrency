import { createAppConfig } from '@nextcloud/vite-config'
import path from 'path'

// https://vite.dev/config/
export default createAppConfig(
  {
    admin: path.resolve(path.join('src', 'admin.ts')),
    user: path.resolve(path.join('src', 'user.ts')),
  },
  {
    config: {
      root: 'src',
      resolve: {
        alias: {
          '@': path.resolve(__dirname, 'src'),
        },
      },
      build: {
        outDir: '../dist',
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
