<?php

namespace App\Ai;

use App\Mcp\Resources\ActivitySearchResource;
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

class ToolRegistry
{
    /**
     * Resource-based tools (read-only data access).
     * Maps tool name → [class, inputSchema].
     */
    private static array $resources = [
        'search_companies' => [
            'class'       => CompaniesListResource::class,
            'description' => 'Search and list companies. Returns paginated results with id, name, primary_domain.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'q'    => ['type' => 'string', 'description' => 'Search query (searches name and domains)'],
                    'page' => ['type' => 'integer', 'description' => 'Page number (default 1)'],
                ],
            ],
        ],
        'get_company' => [
            'class'       => CompanyGetResource::class,
            'description' => 'Get detailed company information including domains, aliases, accounts, brand statuses, and linked people.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Company ID'],
                ],
                'required' => ['id'],
            ],
        ],
        'search_people' => [
            'class'       => PeopleListResource::class,
            'description' => 'Search and list people/contacts. Returns paginated results.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'q'          => ['type' => 'string', 'description' => 'Search query (searches name)'],
                    'page'       => ['type' => 'integer', 'description' => 'Page number (default 1)'],
                    'is_our_org' => ['type' => 'boolean', 'description' => 'Filter by our organization members'],
                ],
            ],
        ],
        'get_person' => [
            'class'       => PersonGetResource::class,
            'description' => 'Get detailed person information including identities and linked companies.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Person ID'],
                ],
                'required' => ['id'],
            ],
        ],
        'search_conversations' => [
            'class'       => ConversationsListResource::class,
            'description' => 'Search conversations. Filter by company, person, or channel type.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'page'         => ['type' => 'integer', 'description' => 'Page number'],
                    'company_id'   => ['type' => 'integer', 'description' => 'Filter by company ID'],
                    'person_id'    => ['type' => 'integer', 'description' => 'Filter by person ID'],
                    'channel_type' => ['type' => 'string', 'description' => 'Filter by channel type (email, slack, discord, ticket)'],
                ],
            ],
        ],
        'get_conversation' => [
            'class'       => ConversationGetResource::class,
            'description' => 'Get conversation details and messages. Use depth parameter to control message loading.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'id'    => ['type' => 'integer', 'description' => 'Conversation ID'],
                    'depth' => ['type' => 'string', 'description' => 'Message depth: headers (default), recent (last 20), full (all)', 'enum' => ['headers', 'recent', 'full']],
                ],
                'required' => ['id'],
            ],
        ],
        'search_activity' => [
            'class'       => ActivitySearchResource::class,
            'description' => 'Search activity timeline. Filter by date range, type, company, or person.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'q'          => ['type' => 'string', 'description' => 'Search query'],
                    'from'       => ['type' => 'string', 'description' => 'Start date (YYYY-MM-DD)'],
                    'to'         => ['type' => 'string', 'description' => 'End date (YYYY-MM-DD)'],
                    'type'       => ['type' => 'string', 'description' => 'Activity type filter'],
                    'company_id' => ['type' => 'integer', 'description' => 'Filter by company'],
                    'person_id'  => ['type' => 'integer', 'description' => 'Filter by person'],
                ],
            ],
        ],
        'list_notes' => [
            'class'       => NotesListResource::class,
            'description' => 'List notes for a specific entity (company or person).',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'entity_type' => ['type' => 'string', 'description' => 'Entity class: App\\Models\\Company or App\\Models\\Person'],
                    'entity_id'   => ['type' => 'integer', 'description' => 'Entity ID'],
                ],
                'required' => ['entity_type', 'entity_id'],
            ],
        ],
        'list_smart_notes' => [
            'class'       => SmartNotesListResource::class,
            'description' => 'List smart notes. Filter by status.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'description' => 'Filter: unrecognized or recognized'],
                    'page'   => ['type' => 'integer', 'description' => 'Page number'],
                ],
            ],
        ],
    ];

    /**
     * Write tools (from MCP Tools).
     */
    private static array $writeTools = [
        CompanyCreateTool::class,
        CompanyUpdateTool::class,
        CompanyAddDomainTool::class,
        CompanyAddAccountTool::class,
        CompanySetBrandStatusTool::class,
        CompanyMergeTool::class,
        PersonCreateTool::class,
        PersonUpdateTool::class,
        PersonAddIdentityTool::class,
        PersonLinkCompanyTool::class,
        PersonMarkOurOrgTool::class,
        PersonMergeTool::class,
        NoteCreateTool::class,
        NoteDeleteTool::class,
        SmartNoteRecognizeTool::class,
        ConversationArchiveTool::class,
    ];

    /**
     * Get all tool definitions in Claude API format.
     */
    public static function toolDefinitions(): array
    {
        $tools = [];

        // Read tools (resources adapted)
        foreach (self::$resources as $name => $config) {
            $tools[] = [
                'name'         => $name,
                'description'  => $config['description'],
                'input_schema' => $config['input_schema'],
            ];
        }

        // Write tools (from MCP tools)
        foreach (self::$writeTools as $toolClass) {
            $descriptor = $toolClass::descriptor();
            $tools[] = [
                'name'         => $descriptor['name'],
                'description'  => $descriptor['description'],
                'input_schema' => $descriptor['inputSchema'],
            ];
        }

        return $tools;
    }

    /**
     * Execute a tool by name. Returns the result array.
     */
    public static function execute(string $toolName, array $params): array
    {
        // Check read tools first
        if (isset(self::$resources[$toolName])) {
            $class = self::$resources[$toolName]['class'];
            return $class::read($params);
        }

        // Check write tools
        foreach (self::$writeTools as $toolClass) {
            $descriptor = $toolClass::descriptor();
            if ($descriptor['name'] === $toolName) {
                return $toolClass::execute($params);
            }
        }

        throw new \InvalidArgumentException("Unknown tool: {$toolName}");
    }
}
