import { createServer } from 'node:http'

import { createHandler } from './app.mjs'

const port = Number.parseInt(process.env.PORT ?? '3000', 10)
if (!Number.isInteger(port) || port < 1 || port > 65535) {
  throw new Error('PORT must be an integer between 1 and 65535.')
}

const handle = createHandler()

function toWebRequest(request) {
  const url = new URL(request.url ?? '/', `http://${request.headers.host ?? 'localhost'}`)
  const init = { method: request.method, headers: request.headers }
  if (request.method !== 'GET' && request.method !== 'HEAD') {
    init.body = request
    init.duplex = 'half'
  }
  return new Request(url, init)
}

const server = createServer(async (request, response) => {
  try {
    const result = await handle(toWebRequest(request))
    response.writeHead(result.status, Object.fromEntries(result.headers.entries()))
    response.end(Buffer.from(await result.arrayBuffer()))
  } catch (error) {
    console.error('Unhandled supabase-server request error:', error)
    response.writeHead(500, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' })
    response.end(JSON.stringify({ success: false, error: { code: 'INTERNAL_ERROR' } }))
  }
})

server.listen(port, '0.0.0.0', () => {
  console.log(`supabase-server listening on port ${port}`)
})
