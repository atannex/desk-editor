# Local collaboration demo environment (source before starting the servers).
export DB_CONNECTION=sqlite
export DB_DATABASE="$(pwd)/workbench/database/database.sqlite"
export APP_URL=http://127.0.0.1:8765
export DESK_BLOG_ENABLED=true
export DESK_EDITOR_COLLABORATION=true
export BROADCAST_CONNECTION=reverb
export REVERB_APP_ID=desk
export REVERB_APP_KEY=desk-local-key
export REVERB_APP_SECRET=desk-local-secret
export REVERB_HOST=127.0.0.1
export REVERB_PORT=8080
export REVERB_SCHEME=http
export APP_NAME="Desk"
export DESK_BLOG_NAME="Desk Journal"
