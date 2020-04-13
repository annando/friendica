<?php
/**
 * @copyright Copyright (C) 2020, Friendica
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace Friendica\Model;
use Friendica\DI;
use Friendica\Core\Logger;
use Friendica\Database\DBA;
use Friendica\Database\DBStructure;
use Friendica\Protocol\Activity;
use Friendica\Util\DateTimeFormat;

class Post
{
	const TABLES = ['post-structure', 'post-content', 'post-thread', 'post-thread-user', 'post-user'];
	const USER_TABLES = ['post-thread-user', 'post-user'];
	const THREAD_TABLES = ['post-thread', 'post-thread-user'];

	const ACTIVITIES = [
		Activity::LIKE, Activity::DISLIKE,
		Activity::ATTEND, Activity::ATTENDNO, Activity::ATTENDMAYBE,
		Activity::FOLLOW,
		Activity::ANNOUNCE];

	private static function getPostFields()
	{
		$definition = DBStructure::definition('', false);

		$postfields = [];
		foreach (self::TABLES as $table) {
			$postfields[$table] = array_keys($definition[$table]['fields']);
		}

		return $postfields;
	}

	private static function prepareFields($fields)
	{
		unset($fields['id']);

		if (!empty($fields['uri']) && empty($fields['uri-id'])) {
			$itemuri_fields = ['uri' => $fields['uri']];
			if (!empty($fields['guid'])) {
				$itemuri_fields['guid'] = $fields['guid'];
			}
			$fields['uri-id'] = ItemURI::insert($itemuri_fields);
			unset($fields['uri']);
			unset($fields['guid']);
		}

		if (!empty($fields['parent-uri']) && empty($fields['parent-uri-id'])) {
			$fields['parent-uri-id'] = ItemURI::insert(['uri' => $fields['parent-uri']]);
			unset($fields['parent-uri']);
		}

		if (!empty($fields['thr-parent']) && empty($fields['thr-parent-id'])) {
			$fields['thr-parent-id'] = ItemURI::insert(['uri' => $fields['thr-parent']]);
			unset($fields['thr-parent']);
		}

		if (!empty($fields['extid']) && empty($fields['external-id'])) {
			$fields['external-id'] = ItemURI::insert(['uri' => $fields['extid']]);
			unset($fields['extid']);
		}

		$fields['parent-uri-id'] = $fields['parent-uri-id'] ?? $fields['uri-id'];
		$fields['thr-parent-id'] = $fields['thr-parent-id'] ?? $fields['parent-uri-id'];
		$fields['wall']          = intval($fields['wall'] ?? 0);
		$fields['author-name']   = trim($fields['author-name'] ?? '');
		$fields['author-link']   = trim($fields['author-link'] ?? '');
		$fields['author-avatar'] = trim($fields['author-avatar'] ?? '');
		$fields['owner-name']    = trim($fields['owner-name'] ?? '');
		$fields['owner-link']    = trim($fields['owner-link'] ?? '');
		$fields['owner-avatar']  = trim($fields['owner-avatar'] ?? '');
		$fields['received']      = (isset($fields['received'])  ? DateTimeFormat::utc($fields['received'])  : DateTimeFormat::utcNow());
		$fields['created']       = (isset($fields['created'])   ? DateTimeFormat::utc($fields['created'])   : $fields['received']);
		$fields['edited']        = (isset($fields['edited'])    ? DateTimeFormat::utc($fields['edited'])    : $fields['created']);
		$fields['changed']       = (isset($fields['changed'])   ? DateTimeFormat::utc($fields['changed'])   : $fields['created']);
		$fields['commented']     = (isset($fields['commented']) ? DateTimeFormat::utc($fields['commented']) : $fields['created']);
		$fields['title']         = substr(trim($fields['title'] ?? ''), 0, 255);
		$fields['location']      = trim($fields['location'] ?? '');
		$fields['coord']         = trim($fields['coord'] ?? '');
		$fields['visible']       = (isset($fields['visible']) ? intval($fields['visible']) : 1);
		$fields['deleted']       = 0;
		$fields['post-type']     = ($fields['post-type'] ?? '') ?: Item::PT_ARTICLE;
		$fields['verb']          = trim($fields['verb'] ?? '');
		$fields['object-type']   = trim($fields['object-type'] ?? '');
		$fields['object']        = trim($fields['object'] ?? '');
		$fields['target-type']   = trim($fields['target-type'] ?? '');
		$fields['target']        = trim($fields['target'] ?? '');
		$fields['plink']         = substr(trim($fields['plink'] ?? ''), 0, 255);
		$fields['allow_cid']     = trim($fields['allow_cid'] ?? '');
		$fields['allow_gid']     = trim($fields['allow_gid'] ?? '');
		$fields['deny_cid']      = trim($fields['deny_cid'] ?? '');
		$fields['deny_gid']      = trim($fields['deny_gid'] ?? '');
		$fields['private']       = intval($fields['private'] ?? Item::PUBLIC);
		$fields['body']          = trim($fields['body'] ?? '');
		$fields['tag']           = trim($fields['tag'] ?? '');
		$fields['attach']        = trim($fields['attach'] ?? '');
		$fields['app']           = trim($fields['app'] ?? '');
		$fields['origin']        = intval($fields['origin'] ?? 0);
		$fields['postopts']      = trim($fields['postopts'] ?? '');
		$fields['resource-id']   = trim($fields['resource-id'] ?? '');
		$fields['event-id']      = intval($fields['event-id'] ?? 0);
		$fields['inform']        = trim($fields['inform'] ?? '');
		$fields['file']          = trim($fields['file'] ?? '');

		if (isset($fields['gravity'])) {
			$fields['gravity'] = intval($fields['gravity']);
		} elseif ($fields['parent-uri-id'] == $fields['uri-id']) {
			$fields['gravity'] = GRAVITY_PARENT;
		} elseif ($fields['verb'] == Activity::POST) {
			$fields['gravity'] = GRAVITY_COMMENT;
		} elseif (in_array($fields['verb'], self::ACTIVITIES)) {
			$fields['gravity'] = GRAVITY_ACTIVITY;		
		} else {
			$fields['gravity'] = GRAVITY_UNKNOWN;   // Should not happen
			Logger::info('Unknown gravity for verb', ['verb' => $fields['verb']]);
		}

		if (empty($fields['vid']) && !empty($fields['verb'])) {
			$fields['vid'] = Verb::getID($fields['verb']);
		}

		if (empty($fields['author-id'])) {
			$default = ['url' => $fields['author-link'], 'name' => $fields['author-name'],
			'photo' => $fields['author-avatar'], 'network' => $fields['network']];

			$fields['author-id'] = ($fields['author-id'] ?? 0) ?: Contact::getIdForURL($fields['author-link'], 0, false, $default);
		}

		unset($fields['author-link']);
		unset($fields['author-avatar']);
		unset($fields['author-name']);

		if (empty($fields['owner-id'])) {
			$default = ['url' => $fields['owner-link'], 'name' => $fields['owner-name'],
			'photo' => $fields['owner-avatar'], 'network' => $fields['network']];

			$fields['owner-id'] = ($fields['owner-id'] ?? 0) ?: Contact::getIdForURL($fields['owner-link'], 0, false, $default);
		}

		unset($fields['owner-link']);
		unset($fields['owner-avatar']);
		unset($fields['owner-name']);

		if (empty($fields['psid'])) {
			$fields['psid'] = PermissionSet::getIdFromACL(
				$fields['uid'],
				$fields['allow_cid'],
				$fields['allow_gid'],
				$fields['deny_cid'],
				$fields['deny_gid']
			);
		}

		unset($fields['allow_cid']);
		unset($fields['allow_gid']);
		unset($fields['deny_cid']);
		unset($fields['deny_gid']);

		// To-Do
		unset($fields['tag']);
		unset($fields['file']);

		return $fields;
	}

	private static function assignTableFields(array $fields, array $condition = [])
	{
		if (empty($condition)) {
			$condition = $fields;
		}

		$structure = self::getPostFields();

		$test = $fields;
		unset($test['verb']);

		$table_fields = [];

		foreach ($fields as $field => $value) {
			if (empty($value)) {
				unset($test[$field]);
			}
			if (is_null($value)) {
				continue;
			}
			foreach (self::TABLES as $table) {
				if (in_array($field, $structure[$table])) {
					$table_fields[$table][$field] = $value;
					unset($test[$field]);
				}
			}
		}
if (!empty($test)) {
	var_dump($test);
	die();
}
		if (!array_key_exists('uid', $condition)) {
			foreach (self::USER_TABLES as $table) {
				unset($table_fields[$table]);
			}
		}

		if ($condition['gravity'] != GRAVITY_PARENT) {
			foreach (self::THREAD_TABLES as $table) {
				unset($table_fields[$table]);
			}
		}

		// When the activity doesn't need a content, we remove it
		if (!empty($condition['verb']) && in_array($condition['verb'], self::ACTIVITIES)) {
			unset($table_fields['post-content']);
		}

		return $table_fields;
	}

	public static function insert(array $fields)
	{
		DBA::transaction();

		$fields = self::prepareFields($fields);
		if (empty($fields['uri-id'])) {
			DBA::rollback();
			return 0;
		}

		$table_fields = self::assignTableFields($fields);

		foreach (self::TABLES as $table) {
			if (empty($table_fields[$table])) {
				continue;
			}
			if (!DBA::insert($table, $table_fields[$table], true)) {
				DBA::rollback();
				return 0;
			}
		}
		DBA::commit();

		return $fields['uri-id'];
	}

	public static function update(array $fields, array $condition, int $uid = null)
	{
		$affected_rows = DBA::select('post-structure', ['uri-id', 'gravity'], $condition);
		if (!DBA::isResult($affected_rows)) {
			return true;
		}

		while ($row = DBA::fetch($affected_rows)) {
			$table_fields = self::assignTableFields($fields, array_merge(['uid' => $uid], $row));

			foreach (self::TABLES as $table) {
				if (empty($table_fields[$table])) {
					continue;
				}

				$condition = ['uri-id' => $row['uri-id']];
				if (in_array($table, self::USER_TABLES) && !is_null($uid)) {
					$condition['uid'] = $uid;
				}

				if (!DBA::update($table, $table_fields[$table], $condition)) {
					DBA::rollback();
					return false;
				}
			}
		}
		DBA::commit();

		return true;
	}
}
