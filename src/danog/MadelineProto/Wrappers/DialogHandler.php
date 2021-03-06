<?php

/**
 * DialogHandler module.
 *
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2018 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 *
 * @link      https://docs.madelineproto.xyz MadelineProto documentation
 */

namespace danog\MadelineProto\Wrappers;

trait DialogHandler
{
    public function get_dialogs($force = true)
    {
        return $this->wait($this->get_dialogs_async($force));
    }
    public function get_dialogs_async($force = true)
    {
        if ($force || !isset($this->dialog_params['offset_date']) || is_null($this->dialog_params['offset_date']) || !isset($this->dialog_params['offset_id']) || is_null($this->dialog_params['offset_id']) || !isset($this->dialog_params['offset_peer']) || is_null($this->dialog_params['offset_peer']) || !isset($this->dialog_params['count']) || is_null($this->dialog_params['count'])) {
            $this->dialog_params = ['limit' => 100, 'offset_date' => 0, 'offset_id' => 0, 'offset_peer' => ['_' => 'inputPeerEmpty'], 'count' => 0, 'hash' => 0];
        }
        if (!isset($this->dialog_params['hash'])) {
            $this->dialog_params['hash'] = 0;
        }
        $this->updates_state['sync_loading'] = true;
        $res = ['dialogs' => [0], 'count' => 1];
        $datacenter = $this->datacenter->curdc;
        $peers = [];
        $this->postpone_updates = true;

        try {
            $this->logger->logger(\danog\MadelineProto\Lang::$current_lang['getting_dialogs']);
            while ($this->dialog_params['count'] < $res['count']) {
                $res = yield $this->method_call_async_read('messages.getDialogs', $this->dialog_params, ['datacenter' => $datacenter, 'FloodWaitLimit' => 100]);
                $last_peer = [];
                $last_date = 0;
                $last_id = 0;
                $res['messages'] = array_reverse($res['messages']);
                foreach (array_reverse($res['dialogs']) as $dialog) {
                    if (!in_array($dialog['peer'], $peers)) {
                        $peers[] = $dialog['peer'];
                    }
                    if (!$last_date) {
                        if (!$last_peer) {
                            $last_peer = $dialog['peer'];
                        }
                        if (!$last_id) {
                            $last_id = $dialog['top_message'];
                        }
                        foreach ($res['messages'] as $message) {
                            if (yield $this->get_info($message)['Peer'] === $last_peer && $last_id === $message['id']) {
                                $last_date = $message['date'];
                                break;
                            }
                        }
                    }
                }
                if ($last_date) {
                    $this->dialog_params['offset_date'] = $last_date;
                    $this->dialog_params['offset_peer'] = $last_peer;
                    $this->dialog_params['offset_id'] = $last_id;
                    $this->dialog_params['count'] = count($peers);
                } else {
                    break;
                }
                if (!isset($res['count'])) {
                    break;
                }
            }
        } finally {
            $this->postpone_updates = false;
            $this->updates_state['sync_loading'] = false;
            $this->handle_pending_updates();
        }

        return $peers;
    }
}
