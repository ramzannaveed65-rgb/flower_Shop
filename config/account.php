<?php
// =====================================================
// config/account.php - Delete a customer account
//
// Google Play requires that a customer can delete the account
// from inside the app AND from a web page. Both use this function.
//
// What happens:
//  - name, mobile number, address, city, password and login are wiped
//  - the cart is emptied
//  - the mobile number becomes free again (the person can register again later)
//  - past orders and bookings stay in the shop's records (needed for
//    accounts and delivery), but they no longer belong to a usable account
// =====================================================

function delete_customer_account(PDO $pdo, int $customer_id): void
{
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM cart_items WHERE customer_id = ?")->execute([$customer_id]);

        // A placeholder that can never be used to log in, and never clashes with a real number
        $placeholder = 'del-' . $customer_id . '-' . bin2hex(random_bytes(3));

        $pdo->prepare(
            "UPDATE customers
             SET name = 'Deleted customer', phone = ?, password_hash = ?, api_token = NULL,
                 address = NULL, city = NULL
             WHERE id = ?"
        )->execute([
            $placeholder,
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            $customer_id,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
