FROM node:22-alpine

WORKDIR /app

# Copy manifests and install dependencies
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm install

# Vite dev server on 0.0.0.0 for host access
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
