<?php
// =====================================================
// privacy.php - Privacy Policy (public web page)
//
// Google Play asks for a link to this page:
//   https://YOUR-DOMAIN/privacy.php
// The shop name and contact come from Admin -> Settings.
// =====================================================

require __DIR__ . '/site/_init.php';

$contact = trim($settings['support_contact'] ?? '') ?: trim($settings['shop_phone'] ?? '');

$updated = '7 October 2026';     // change this date when you change the text below

$page_title = 'Privacy Policy';
require __DIR__ . '/site/_header.php';
?>
<div class="container">
<div class="page-head"><h1>Privacy Policy</h1><p>Last updated: <?= e($updated) ?></p></div>
<div class="policy">

<p>This policy explains what information the <b><?= e($shop_name) ?></b> website and mobile app collect,
why we collect it and what choices you have. By using them you agree to this policy.</p>

<h3>1. Information we collect</h3>
<p>We only collect what we need to take and deliver your order:</p>
<ul>
  <li><b>Account details:</b> your name, mobile number, address and city, and a password
      (the password is stored in a protected, unreadable form).</li>
  <li><b>Order details:</b> the items you order, the receiver's name, mobile number and
      delivery address, the delivery date and any gift message you write.</li>
  <li><b>Payment proof:</b> if you pay in advance by NayaPay or Easypaisa, the transaction ID
      and the payment screenshot you choose to upload. We never see or store your card number,
      wallet PIN or bank password.</li>
  <li><b>Event bookings:</b> the event type, date, venue, number of guests, contact name and
      number, and your notes.</li>
</ul>
<p>We do <b>not</b> collect your location, contacts, messages or any other files from your phone or computer.
A photo is only uploaded when you choose a payment screenshot yourself. The website uses one cookie to keep
you logged in and to remember your cart.</p>

<h3>2. How we use your information</h3>
<ul>
  <li>To create your account and let you log in.</li>
  <li>To prepare, deliver and confirm your orders and event bookings.</li>
  <li>To check advance payments.</li>
  <li>To call or message you about your order or booking.</li>
</ul>
<p>We do not use your information for advertising and we do not sell it to anyone.</p>

<h3>3. Who we share it with</h3>
<ul>
  <li><b>Delivery staff:</b> the receiver's name, mobile number and address, so the order can be delivered.</li>
  <li><b>Service providers:</b> the company that hosts our server, and a messaging service that
      forwards new order details to the shop's own WhatsApp number so we can act on orders quickly.</li>
  <li><b>Authorities:</b> only when the law requires it.</li>
</ul>

<h3>4. How we protect it</h3>
<p>The website and app talk to our server over an encrypted (HTTPS) connection. Passwords are stored in a
protected form that cannot be read. Only the shop's staff can see orders. No system is perfectly
secure, but we take reasonable care of your information.</p>

<h3>5. How long we keep it</h3>
<p>We keep your account for as long as you use it. Order, payment and booking records are kept
for our accounts and to deal with complaints, even after an account is deleted.</p>

<h3>6. Deleting your account</h3>
<p>You can delete your account at any time:</p>
<ul>
  <li>On the website: <a href="<?= url('delete-account.php') ?>">Delete my account</a>.</li>
  <li>In the app: open the menu on the home screen, tap <b>Account</b>, then <b>Delete my account</b>.</li>
</ul>
<p>Deleting your account removes your name, mobile number, address, city, password and cart from
our system. Records of orders and bookings you already placed are kept as explained in section 5.</p>

<h3>7. Children</h3>
<p>Our service is meant for adults. We do not knowingly collect information from children under 13.</p>

<h3>8. Changes to this policy</h3>
<p>If we change this policy, we will put the new version on this page and change the date at the top.</p>

<h3>9. Contact us</h3>
<?php if ($contact !== ''): ?>
  <p>Questions about your information? Contact <b><?= e($shop_name) ?></b>: <?= e($contact) ?></p>
<?php else: ?>
  <p>Questions about your information? Contact <b><?= e($shop_name) ?></b> through the phone number
  shown on your order confirmation.</p>
<?php endif; ?>
</div>
</div>
<?php require __DIR__ . '/site/_footer.php'; ?>
