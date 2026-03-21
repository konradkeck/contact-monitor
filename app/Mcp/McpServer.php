<?php

namespace App\Mcp;

use App\Mcp\Resources\ActivitySearchResource;
use App\Mcp\Resources\AuditLogResource;
use App\Mcp\Resources\CompaniesListResource;
use App\Mcp\Resources\CompanyGetResource;
use App\Mcp\Resources\ConversationGetResource;
use App\Mcp\Resources\ConversationsListResource;
use App\Mcp\Resources\NotesListResource;
use App\Mcp\Resources\PeopleListResource;
use App\Mcp\Resources\PersonGetResource;
use App\Mcp\Resources\SmartNotesListResource;
use App\Mcp\Tools\CompanyAddAccountTool;
use App\Mcp\Tools\CompanyAddDomainTool;
use App\Mcp\Tools\CompanyCreateTool;
use App\Mcp\Tools\CompanyMergeTool;
use App\Mcp\Tools\CompanySetBrandStatusTool;
use App\Mcp\Tools\CompanyUpdateTool;
use App\Mcp\Tools\ConversationArchiveTool;
use App\Mcp\Tools\NoteCreateTool;
use App\Mcp\Tools\NoteDeleteTool;
use App\Mcp\Tools\PersonAddIdentityTool;
use App\Mcp\Tools\PersonCreateTool;
use App\Mcp\Tools\PersonLinkCompanyTool;
use App\Mcp\Tools\PersonMarkOurOrgTool;
use App\Mcp\Tools\PersonMergeTool;
use App\Mcp\Tools\PersonUpdateTool;
use App\Mcp\Tools\SmartNoteRecognizeTool;
use App\Models\McpLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class McpServer
{
    private const SERVER_NAME    = 'Contact Monitor MCP';
    private const SERVER_VERSION = '1.0.0';

    /** @var array<string, class-string> URI prefix → resource class */
    private array $resources = [
        'companies://list'         => CompaniesListResource::class,
        'companies://'             => CompanyGetResource::class,
        'people://list'            => PeopleListResource::class,
        'people://'                => PersonGetResource::class,
        'conversations://list'     => ConversationsListResource::class,
        'conversations://'         => ConversationGetResource::class,
        'activity://search'        => ActivitySearchResource::class,
        'notes://list'             => NotesListResource::class,
        'smart_notes://list'       => SmartNotesListResource::class,
        'audit_log://list'         => AuditLogResource::class,
    ];

    /** @var array<string, class-string> tool name → tool class */
    private array $tools = [
        'company_create'         => CompanyCreateTool::class,
        'company_update'         => CompanyUpdateTool::class,
        'company_add_domain'     => CompanyAddDomainTool::class,
        'company_add_account'    => CompanyAddAccountTool::class,
        'company_set_brand_status' => CompanySetBrandStatusTool::class,
        'company_merge'          => CompanyMergeTool::class,
        'person_create'          => PersonCreateTool::class,
        'person_update'          => PersonUpdateTool::class,
        'person_add_identity'    => PersonAddIdentityTool::class,
        'person_link_company'    => PersonLinkCompanyTool::class,
        'person_mark_our_org'    => PersonMarkOurOrgTool::class,
        'person_merge'           => PersonMergeTool::class,
        'note_create'            => NoteCreateTool::class,
        'note_delete'            => NoteDeleteTool::class,
        'smart_note_recognize'   => SmartNoteRecognizeTool::class,
        'conversation_archive'   => ConversationArchiveTool::class,
    ];

    public function handle(array $request): array
    {
        $method = $request['method'] ?? null;
        $id     = $request['id'] ?? null;
        $params = $request['params'] ?? [];

        try {
            $result = match ($method) {
                'initialize'       => $this->initialize($params),
                'resources/list'   => $this->resourcesList(),
                'resources/read'   => $this->resourcesRead($params),
                'tools/list'       => $this->toolsList(),
                'tools/call'       => $this->toolsCall($params),
                'ping'             => [],
                default            => throw new McpException(-32601, "Method not found: {$method}"),
            };
        } catch (McpException $e) {
            return $this->errorResponse($id, $e->getCode(), $e->getMessage());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse($id, -32602, 'Record not found');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($id, -32602, $e->getMessage());
        } catch (\Throwable $e) {
            return $this->errorResponse($id, -32603, 'Internal error: ' . $e->getMessage());
        }

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ];
    }

    private function initialize(array $params): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'capabilities'    => [
                'resources' => ['subscribe' => false, 'listChanged' => false],
                'tools'     => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name'    => self::SERVER_NAME,
                'version' => self::SERVER_VERSION,
            ],
        ];
    }

    private function resourcesList(): array
    {
        $list = [];

        $list[] = CompaniesListResource::descriptor();
        $list[] = CompanyGetResource::descriptor();
        $list[] = PeopleListResource::descriptor();
        $list[] = PersonGetResource::descriptor();
        $list[] = ConversationsListResource::descriptor();
        $list[] = ConversationGetResource::descriptor();
        $list[] = ActivitySearchResource::descriptor();
        $list[] = NotesListResource::descriptor();
        $list[] = SmartNotesListResource::descriptor();
        $list[] = AuditLogResource::descriptor();

        return ['resources' => $list];
    }

    private function resourcesRead(array $params): array
    {
        $uri = $params['uri'] ?? throw new McpException(-32602, 'Missing uri param');

        [$class, $resolvedParams] = $this->resolveResource($uri, $params['params'] ?? []);

        $data = $class::read($resolvedParams);

        return [
            'contents' => [
                [
                    'uri'      => $uri,
                    'mimeType' => 'application/json',
                    'text'     => json_encode($data),
                ],
            ],
        ];
    }

    private function resolveResource(string $uri, array $extraParams): array
    {
        // Exact match first
        if (isset($this->resources[$uri])) {
            return [$this->resources[$uri], $extraParams];
        }

        // Pattern match: companies://123 → CompanyGetResource with id=123
        if (preg_match('#^companies://(\d+)$#', $uri, $m)) {
            return [CompanyGetResource::class, array_merge($extraParams, ['id' => (int) $m[1]])];
        }
        if (preg_match('#^people://(\d+)$#', $uri, $m)) {
            return [PersonGetResource::class, array_merge($extraParams, ['id' => (int) $m[1]])];
        }
        if (preg_match('#^conversations://(\d+)$#', $uri, $m)) {
            return [ConversationGetResource::class, array_merge($extraParams, ['id' => (int) $m[1]])];
        }

        throw new McpException(-32602, "Unknown resource URI: {$uri}");
    }

    private function toolsList(): array
    {
        $list = [];
        foreach ($this->tools as $toolClass) {
            $list[] = $toolClass::descriptor();
        }
        return ['tools' => $list];
    }

    private function toolsCall(array $params): array
    {
        $toolName  = $params['name'] ?? throw new McpException(-32602, 'Missing tool name');
        $toolInput = $params['arguments'] ?? [];
        $context   = $toolInput['_context'] ?? 'unknown';

        if (!isset($this->tools[$toolName])) {
            throw new McpException(-32602, "Unknown tool: {$toolName}");
        }

        $toolClass = $this->tools[$toolName];

        // Confirmation flow for chat context
        if ($context === 'chat' && !isset($toolInput['_confirm_token'])) {
            $token = Str::random(32);
            Cache::put('mcp_confirm_' . $token, ['tool' => $toolName, 'input' => $toolInput], 60);

            return [
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'confirmation_required' => true,
                        'description'           => $this->confirmationDescription($toolName, $toolInput),
                        'confirm_token'         => $token,
                    ]),
                ]],
            ];
        }

        // Validate confirm token if provided
        if (isset($toolInput['_confirm_token'])) {
            $token    = $toolInput['_confirm_token'];
            $cached   = Cache::pull('mcp_confirm_' . $token);
            if (!$cached || $cached['tool'] !== $toolName) {
                throw new McpException(-32602, 'Invalid or expired confirmation token');
            }
            $toolInput = $cached['input'];
            $context   = 'chat';
        }

        // Strip meta params before passing to tool
        unset($toolInput['_context'], $toolInput['_confirm_token']);

        $result = $toolClass::execute($toolInput);

        McpLog::record($toolName, $toolInput, $result, $context);

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($result),
            ]],
        ];
    }

    private function confirmationDescription(string $toolName, array $input): string
    {
        $label = str_replace('_', ' ', $toolName);
        $key   = array_key_first(array_filter($input, fn($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY));
        $val   = $key ? ($input[$key] ?? '') : '';
        return ucfirst($label) . ($val ? ": {$val}" : '');
    }

    private function errorResponse(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => ['code' => $code, 'message' => $message],
        ];
    }
}
