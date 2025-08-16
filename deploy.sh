#!/bin/bash

# DigitalOcean Deployment Script for Stolen Phone Database
# This script automates the deployment process

set -e

echo "🚀 Starting deployment to DigitalOcean..."

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Create SSL directory if it doesn't exist
mkdir -p ssl

# Build and start the application
echo "📦 Building and starting the application..."
docker-compose -f docker-compose.prod.yml down
docker-compose -f docker-compose.prod.yml build --no-cache
docker-compose -f docker-compose.prod.yml up -d

# Wait for the application to start
echo "⏳ Waiting for the application to start..."
sleep 30

# Check if the application is running
echo "🔍 Checking application health..."
if curl -f http://localhost/api/health > /dev/null 2>&1; then
    echo "✅ Application is running successfully!"
    echo "🌐 Your application is now accessible at: http://$(curl -s ifconfig.me)"
    echo "📊 Admin panel: http://$(curl -s ifconfig.me)/admin"
else
    echo "❌ Application failed to start. Checking logs..."
    docker-compose -f docker-compose.prod.yml logs app
    exit 1
fi

echo "🎉 Deployment completed successfully!"
echo ""
echo "📋 Useful commands:"
echo "  View logs: docker-compose -f docker-compose.prod.yml logs -f"
echo "  Stop app: docker-compose -f docker-compose.prod.yml down"
echo "  Restart app: docker-compose -f docker-compose.prod.yml restart"
echo "  Update app: ./deploy.sh"
