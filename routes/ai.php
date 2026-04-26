<?php

use Laravel\Mcp\Facades\Mcp;
use XetaSuite\Mcp\Servers\XetaSuiteServer;

Mcp::web('/mcp', XetaSuiteServer::class)->middleware('auth:sanctum');
