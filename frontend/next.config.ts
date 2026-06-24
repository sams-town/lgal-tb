import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: 'export',
  trailingSlash: true,
  basePath: '/new-hospital/dist',
  assetPrefix: '/new-hospital/dist/',
};

export default nextConfig;
