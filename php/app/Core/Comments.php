<?php
/**
 * Profile comment threads with reactions.
 * PHP port of getProfileComments() / canDeleteComment() from src/routes/web.routes.js.
 */
final class Comments
{
    public const REACTION_TYPES = ['like', 'love', 'laugh'];

    /**
     * Threaded comment tree for a profile.
     * @return array{tree: array<int,object>, total: int}
     */
    public static function profileTree(int $profileUserId, ?int $meId = null): array
    {
        $rows = DB::all(
            'SELECT c.*, u.username, u.profile_pic, u.role
             FROM profile_comments c JOIN users u ON u.id = c.author_id
             WHERE c.profile_user_id = ?
             ORDER BY c.created_at ASC, c.id ASC',
            [$profileUserId]
        );

        $rx = [];
        if ($rows) {
            $ids = array_map(fn($r) => (int) $r->id, $rows);
            $q = implode(',', array_fill(0, count($ids), '?'));
            $rrows = DB::all(
                "SELECT comment_id, type, COUNT(*) n, SUM(user_id = ?) mine
                 FROM comment_reactions WHERE comment_id IN ($q)
                 GROUP BY comment_id, type",
                array_merge([$meId ?? -1], $ids)
            );
            foreach ($rrows as $r) {
                $rx[(int) $r->comment_id][] = [
                    'type' => (string) $r->type,
                    'count' => (int) $r->n,
                    'mine' => !empty($r->mine),
                ];
            }
        }

        $map = [];
        foreach ($rows as $r) {
            $node = clone $r;
            $node->time_ago = nh_time_ago((string) $r->created_at);
            $node->reactions = $rx[(int) $r->id] ?? [];
            $node->replies = [];
            $map[(int) $r->id] = $node;
        }

        $roots = [];
        foreach ($rows as $r) {
            $node = $map[(int) $r->id];
            $pid = (int) ($r->parent_id ?? 0);
            if ($pid && isset($map[$pid])) $map[$pid]->replies[] = $node;
            else $roots[] = $node;
        }

        return ['tree' => array_reverse($roots), 'total' => count($rows)];
    }

    /** Admin, the author, or the profile owner may delete. */
    public static function canDelete($c, ?object $user): bool
    {
        if (!$user || !$c) return false;
        $uid = (int) ($user->id ?? 0);
        return ($user->role ?? '') === 'admin'
            || $uid === (int) ($c->author_id ?? 0)
            || $uid === (int) ($c->profile_user_id ?? 0);
    }
}
