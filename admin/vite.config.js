import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  // Production on MilesWeb lives at https://vibhaajewellery.in/admin/
  base: process.env.VITE_BASE || '/',
  server: {
    port: 5174,
    open: false,
  },
});
