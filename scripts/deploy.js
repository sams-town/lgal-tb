const fs = require('fs');
const path = require('path');

const rootDir = path.join(__dirname, '..');
const frontendOutDir = path.join(rootDir, 'frontend', 'out');
const apiDir = path.join(rootDir, 'api');
const distDir = path.join(rootDir, 'dist');

if (fs.existsSync(distDir)) {
  fs.rmSync(distDir, { recursive: true });
}

fs.mkdirSync(distDir, { recursive: true });

function copyDir(src, dest) {
  fs.mkdirSync(dest, { recursive: true });
  const entries = fs.readdirSync(src, { withFileTypes: true });

  for (const entry of entries) {
    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);

    if (entry.isDirectory()) {
      copyDir(srcPath, destPath);
    } else {
      fs.copyFileSync(srcPath, destPath);
    }
  }
}

if (fs.existsSync(frontendOutDir)) {
  copyDir(frontendOutDir, distDir);
}

if (fs.existsSync(apiDir)) {
  copyDir(apiDir, path.join(distDir, 'api'));
}

// Copy .htaccess.dist to dist/.htaccess
const htaccessPath = path.join(rootDir, '.htaccess.dist');
if (fs.existsSync(htaccessPath)) {
  fs.copyFileSync(htaccessPath, path.join(distDir, '.htaccess'));
}

console.log('Deployment complete! Dist folder ready for upload.');
