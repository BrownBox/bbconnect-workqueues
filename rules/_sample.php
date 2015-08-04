<?php
// This is a basic example of what you can do with work queue rules.
// Of course the various applications are limited only by your imagination

// Evaluate $args & check for required variables
is_array( $args ) ? extract( $args ) : parse_str( $args ); // You need this line in every rule
if (empty( $amount ) || empty( $receipt_number ) || empty( $author_id ) ) return; // Some basic validation

// condition { action } -- All Donations
if( $receipt_number ) {

    // setup the standard description for all donation notes.
    $description  = 'Receipt Number: '.$receipt_number.'<br>'."\n";
    $description .= 'Donation : $'.number_format($amount, 2).'<br>'."\n";
    $donations = number_format($amount, 2);
    if( !empty( $transaction_date ) ) $description .= 'Transaction Date: '.$transaction_date.'<br>'."\n";
    if( !empty( $source ) ) $description .= 'Source: '.$source.'<br>'."\n";
    if( !empty( $frequency ) ) $description .= 'Payment Frequency: '.$frequency.'<br>'."\n";
    if( !empty( $offline_payment_method ) ) $description .= 'Payment Method: '.$offline_payment_method.'<br>'."\n";
    if( !empty( $payment_method ) ) $description .= 'Payment Method: '.$payment_method.'<br>'."\n";
    if( !empty( $bsb ) ) $description .= 'BSB Number: '.$bsb.'<br>'."\n";
    if( !empty( $cheque_number ) ) $description .= 'BSB Number: '.$cheque_number.'<br>'."\n";
    if( isset( $tax_deductible ) ) $description .= ($tax_deductible) ? 'Tax deductible: Yes' : 'Tax deductible: No'.'<br>'."\n";

    // create a note w/out action
    bbconnect_workqueues_insert_action_item( $author_id, $args["payment_method"].' Donation', $description, 'donation', $receipt_number );

    // if they where inactive, make active and update communication preferences.
    $active = get_user_meta($author_id, 'active', true);
    if( $active == 'false' ) {
        update_user_meta($author_id, 'receives_letters', 'true');
        update_user_meta($author_id, 'receives_newsletters', 'true');
    }
    update_user_meta($author_id, 'active', 'true');
}

// condition { action } -- Only offline donations
if ( $offline == true && $frequency == 'one-off' ) {
    $description = 'We need to print receipt number '.$receipt_number;
    // create a note w/ action
    bbconnect_workqueues_insert_action_item( $author_id, 'Printed receipt required', $description, 'receipt-to-print', $receipt_number, true );
}

// condition { action } -- If the donation is large
if ($amount >= 500 ) {
    // create a note w/ action
    bbconnect_workqueues_insert_action_item( $author_id, 'Large Donation', 'Arrange a phone call/email to thank the donor for a $'.$donations.' AUD donation.', 'large-donation', $receipt_number, true );
}

// condition { action } -- If the donation is recurring
if (!empty($frequency) && preg_replace('/[^a-z0-9]/', '', strtolower($frequency)) != 'oneoff') {
    $regular_donor = get_user_meta($author_id, 'regular_donor', true);
    if ($regular_donor !== 'true' ) {
        bbconnect_workqueues_insert_action_item($author_id, 'New Recurring Donor', 'Arrange a phone call to thank the donor for initiating a recurring donation of $'.$donations.' AUD/'.$frequency.'.', 'new-recurring-donor', $receipt_number, true );
        update_user_meta($author_id, 'regular_donor', 'true');
    }
}