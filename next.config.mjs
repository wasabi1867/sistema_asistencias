/** @type {import('next').NextConfig} */
const nextConfig = {
  // Hostinger sirve archivos estáticos y PHP: la página se exporta a out/ y la API va en out/api.
  output: 'export',
  images: {
    unoptimized: true,
  },
}

export default nextConfig
