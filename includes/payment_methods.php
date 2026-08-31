<?php
/**
 * Centralized Payment Methods Configuration
 * Use this file across all modules for consistent payment method options
 */

// Standard payment methods available across the system
$PAYMENT_METHODS = [
    'company_cash' => 'Company Cash',
    'company_bank' => 'Company Bank Transfer',
    'company_card' => 'Company Card',
    'company_cheque' => 'Company Cheque',
    'credit_card' => 'Credit Card',
    'personal' => 'Personal / Employee Cash (Reimbursable)',
    'rahees_cash_card' => 'Rahees Cash / Card',
    'salman_cash_card' => 'Salman Cash / Card',
    'other' => 'Add Other'
];

/**
 * Get all payment methods
 * @return array Associative array of payment method key => label
 */
function get_payment_methods()
{
    global $PAYMENT_METHODS;
    return $PAYMENT_METHODS;
}

/**
 * Get payment method label by key
 * @param string $key Payment method key
 * @return string Payment method label or formatted key if not found
 */
function get_payment_method_label($key)
{
    global $PAYMENT_METHODS;
    return $PAYMENT_METHODS[$key] ?? ucwords(str_replace('_', ' ', $key));
}

/**
 * Check if payment method requires cheque details
 * @param string $method Payment method key
 * @return bool
 */
function payment_method_needs_cheque($method)
{
    return $method === 'company_cheque';
}

/**
 * Check if payment method is reimbursable
 * @param string $method Payment method key
 * @return bool
 */
function payment_method_is_reimbursable($method)
{
    return $method === 'personal';
}

/**
 * Check if payment method is a company payment
 * @param string $method Payment method key
 * @return bool
 */
function payment_method_is_company($method)
{
    return strpos($method, 'company') === 0;
}

/**
 * Generate HTML select options for payment methods
 * @param string|null $selected Currently selected value
 * @param bool $include_empty Include empty first option
 * @return string HTML options
 */
function payment_method_options($selected = null, $include_empty = true)
{
    global $PAYMENT_METHODS;
    $html = '';
    if ($include_empty) {
        $html .= '<option value="">Select Payment Method</option>';
    }
    foreach ($PAYMENT_METHODS as $key => $label) {
        $sel = ($selected === $key) ? ' selected' : '';
        $html .= "<option value=\"{$key}\"{$sel}>{$label}</option>";
    }
    return $html;
}

/**
 * Get icon class for payment method
 * @param string $key Payment method key
 * @return string FontAwesome icon class
 */
function get_payment_method_icon($key)
{
    $icons = [
        'company_cash' => 'fa-money-bill',
        'company_bank' => 'fa-university',
        'company_card' => 'fa-credit-card',
        'company_cheque' => 'fa-money-check',
        'credit_card' => 'fa-credit-card',
        'personal' => 'fa-user',
        'rahees_cash_card' => 'fa-wallet',
        'salman_cash_card' => 'fa-wallet',
        'other' => 'fa-question-circle'
    ];
    return $icons[$key] ?? 'fa-money-bill';
}

