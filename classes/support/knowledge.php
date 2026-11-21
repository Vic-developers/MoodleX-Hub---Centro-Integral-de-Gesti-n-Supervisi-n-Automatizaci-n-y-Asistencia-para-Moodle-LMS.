<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * MoodleX Hub.
 *
 * @package    local_moodlexhub
 * @copyright  2026 MoodleX
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlexhub\support;

use context_system;

/**
 * Shared permission and type catalogue for support articles.
 */
final class knowledge {
    /** @var string XMLDB table for this module's records. */
    public const TABLE = 'local_moodlexhub_article';
    /** @var string Capability to administer this module. */
    public const MANAGE = 'local/moodlexhub:manageknowledgebase';
    /** @var string[] Supported content types. */
    public const TYPES = ['article', 'faq', 'tutorial'];

    /**
     * Authorize readers and editors of the knowledge base.
     */
    public static function require_access(): void {
        \local_moodlexhub\module_config::require_enabled('support');
        $context = context_system::instance();
        if (!has_any_capability([tickets::USE, tickets::RESPOND, tickets::MANAGE, self::MANAGE], $context)) {
            require_capability(tickets::USE, $context);
        }
    }

    /**
     * Return the title and contents of an article that has been published.
     *
     * @param int $id Article identifier.
     * @return \stdClass Published article text.
     */
    public static function published(int $id): \stdClass {
        global $DB;
        self::require_access();
        return $DB->get_record(self::TABLE, ['id' => $id, 'published' => 1], 'id,title,body', MUST_EXIST);
    }
}
