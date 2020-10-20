<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Contains helper class for the payment subsystem.
 *
 * @package    core_payment
 * @copyright  2019 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_payment;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for the payment subsystem.
 *
 * @copyright  2019 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Returns an accumulated list of supported currencies by all payment gateways.
     *
     * @return string[] An array of the currency codes in the three-character ISO-4217 format
     */
    public static function get_supported_currencies(): array {
        $currencies = [];

        $plugins = \core_plugin_manager::instance()->get_enabled_plugins('pg');
        foreach ($plugins as $plugin) {
            $classname = '\pg_' . $plugin . '\gateway';

            $currencies += $classname::get_supported_currencies();
        }

        $currencies = array_unique($currencies);

        return $currencies;
    }

    /**
     * Returns the list of gateways that can process payments in the given currency.
     *
     * @param string $currency The currency in the three-character ISO-4217 format.
     * @return string[]
     */
    public static function get_gateways_for_currency(string $currency): array {
        $gateways = [];

        $plugins = \core_plugin_manager::instance()->get_enabled_plugins('pg');
        foreach ($plugins as $plugin) {
            $classname = '\pg_' . $plugin . '\gateway';

            $currencies = $classname::get_supported_currencies();
            if (in_array($currency, $currencies)) {
                $gateways[] = $plugin;
            }
        }

        return $gateways;
    }

    /**
     * Requires the JS libraries for the pay button.
     */
    public static function gateways_modal_requirejs(): void {
        global $PAGE;

        static $done = false;
        if ($done) {
            return;
        }

        $PAGE->requires->js_call_amd('core_payment/gateways_modal', 'registerEventListeners', ['#gateways-modal-trigger']);
        $done = true;
    }

    /**
     * Returns the attributes to place on a pay button.
     *
     * @param float $amount Amount of payment
     * @param string $currency Currency of payment
     * @return array
     */
    public static function gateways_modal_link_params(float $amount, string $currency) : array {
        return [
            'id' => 'gateways-modal-trigger',
            'role' => 'button',
            'data-amount' => $amount,
            'data-currency' => $currency,
        ];
    }
}
