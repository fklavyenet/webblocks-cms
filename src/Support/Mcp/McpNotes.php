<?php

namespace WebBlocks\Cms\Support\Mcp;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Actions\Notes\ManageNote;
use WebBlocks\Cms\Http\Requests\NoteRequest;
use WebBlocks\Cms\Queries\NoteQuery;
use WebBlocks\Cms\Support\NotePayload;

final class McpNotes
{
  private const LIST = 'list_notes';

  private const GET = 'get_note';

  private const EDIT = 'get_note_for_edit';

  private const RECIPIENTS = 'list_note_recipients';

  public const CREATE = 'create_note';

  public const UPDATE = 'update_note';

  private const DELETE = 'delete_note';

  public const READ_STATE = 'set_note_read_state';

  public const MAX_REQUEST_BYTES = 128 * 1024;

  public const MUTATIONS = [self::CREATE, self::UPDATE, self::DELETE, self::READ_STATE];

  public function __construct(
    private readonly NoteQuery $notes,
    private readonly ManageNote $actions,
  ) {}

  public static function scope(string $tool): ?string
  {
    return match ($tool) {
      self::LIST, self::GET, self::READ_STATE => McpConfiguration::NOTES_READ,
      self::EDIT, self::RECIPIENTS, self::CREATE, self::UPDATE => McpConfiguration::NOTES_WRITE,
      self::DELETE => McpConfiguration::NOTES_DELETE,
      default => null,
    };
  }

  public function descriptors(): array
  {
    $id = ['note_id' => ['type' => 'integer', 'minimum' => 1]];
    $fields = [
      'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 180],
      'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => NoteRequest::MAX_BODY],
      'recipient_id' => ['type' => ['integer', 'null'], 'minimum' => 1],
      'visibility' => ['type' => 'string', 'enum' => ['general', 'private']],
    ];

    return [
      self::LIST => [[
        'search' => ['type' => 'string', 'maxLength' => 180],
        'audience' => ['type' => 'string', 'enum' => ['general', 'private', 'administration']],
        'status' => ['type' => 'string', 'enum' => ['read', 'unread']],
        'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100000],
        'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
      ], []],
      self::GET => [$id, ['note_id']],
      self::EDIT => [$id, ['note_id']],
      self::RECIPIENTS => [(object) [], []],
      self::CREATE => [$fields, ['title', 'body']],
      self::UPDATE => [$id + $fields, ['note_id']],
      self::DELETE => [$id, ['note_id']],
      self::READ_STATE => [$id + ['read' => ['type' => 'boolean']], ['note_id', 'read']],
    ];
  }

  public function call(string $tool, array $arguments, User $actor): array
  {
    $idRules = ['note_id' => ['required', 'integer', 'min:1']];
    $rules = match ($tool) {
      self::LIST => NoteRequest::filterRules(),
      self::CREATE => NoteRequest::writeRules(true),
      self::UPDATE => $idRules + NoteRequest::writeRules(false),
      self::READ_STATE => $idRules + NoteRequest::readRules(),
      self::RECIPIENTS => [],
      default => $idRules,
    };

    if (array_diff_key($arguments, $rules) !== []) {
      throw new McpToolException('invalid_note_arguments');
    }

    try {
      $input = Validator::make($arguments, $rules)->validate();
      $id = isset($input['note_id']) ? (int) $input['note_id'] : null;

      return match ($tool) {
        self::LIST => NotePayload::listing($this->notes->listing($actor, $input), ($input['audience'] ?? null) === 'administration'),
        self::GET, self::EDIT => $this->detail($actor, $id, $tool === self::EDIT),
        self::RECIPIENTS => ['recipients' => $this->notes->recipients()->map->only(['id', 'name'])->all()],
        self::CREATE, self::UPDATE => ['note' => NotePayload::record($this->actions->save($actor, $input, $id), editContext: true)],
        self::DELETE => $this->delete($actor, $id),
        self::READ_STATE => $this->readState($actor, $id, (bool) $input['read']),
      };
    } catch (ValidationException) {
      throw new McpToolException('invalid_note_arguments');
    } catch (ModelNotFoundException) {
      throw new McpToolException('note_unavailable');
    } catch (HttpException $exception) {
      throw match ($exception->getStatusCode()) {
        403 => new McpToolException('notes_forbidden'),
        404 => new McpToolException('note_unavailable'),
        default => $exception,
      };
    }
  }

  private function detail(User $actor, int $id, bool $edit): array
  {
    $note = $this->notes->details($this->notes->find($actor, $id, $edit ? 'update' : 'view'), $edit ? null : $actor);

    return ['note' => NotePayload::record($note, includeBody: true, editContext: $edit)];
  }

  private function delete(User $actor, int $id): array
  {
    $this->actions->delete($actor, $id);

    return ['deleted' => true];
  }

  private function readState(User $actor, int $id, bool $read): array
  {
    $this->actions->readState($actor, $id, $read);

    return ['id' => $id, 'read' => $read];
  }
}
