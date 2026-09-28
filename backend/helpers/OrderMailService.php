<?php

declare(strict_types=1);

/**
 * Sends order emails via configured SMTP (Mailer / PHPMailer):
 * - Customer: invoice / order confirmation
 * - Owner: new order notification
 */
final class OrderMailService
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Send customer invoice + owner notification after a paid order.
     * Safe to call more than once — uses orders.email_notified_at to avoid duplicates.
     *
     * @return array{customer: bool, owner: bool, skipped: bool}
     */
    public function notifyPaidOrder(int $orderId): array
    {
        SchemaGuard::ensureOrderEmailNotifiedAt($this->db);

        $order = $this->loadOrder($orderId);
        if (!$order) {
            return ['customer' => false, 'owner' => false, 'skipped' => true];
        }

        if (!empty($order['email_notified_at'])) {
            return ['customer' => false, 'owner' => false, 'skipped' => true];
        }

        if (($order['payment_status'] ?? '') !== 'paid') {
            return ['customer' => false, 'owner' => false, 'skipped' => true];
        }

        $items = (new Order($this->db))->getItems($orderId);

        return $this->dispatchOrderEmails(
            $order,
            $items,
            'Your Vibhaa Jewellery order ' . $order['order_number'],
            'New Vibhaa Jewellery order ' . $order['order_number']
        );
    }

    /**
     * Send customer + owner emails after a COD order is placed (payment still pending).
     *
     * @return array{customer: bool, owner: bool, skipped: bool}
     */
    public function notifyCodOrder(int $orderId): array
    {
        SchemaGuard::ensureOrderEmailNotifiedAt($this->db);

        $order = $this->loadOrder($orderId);
        if (!$order) {
            return ['customer' => false, 'owner' => false, 'skipped' => true];
        }

        if (!empty($order['email_notified_at'])) {
            return ['customer' => false, 'owner' => false, 'skipped' => true];
        }

        if (($order['payment_method'] ?? '') !== 'cod') {
            return ['customer' => false, 'owner' => false, 'skipped' => true];
        }

        $items = (new Order($this->db))->getItems($orderId);

        return $this->dispatchOrderEmails(
            $order,
            $items,
            'Your Vibhaa Jewellery COD order ' . $order['order_number'],
            'New Vibhaa Jewellery COD order ' . $order['order_number']
        );
    }

    /**
     * @param array<string, mixed> $order
     * @param list<array<string, mixed>> $items
     * @return array{customer: bool, owner: bool, skipped: bool}
     */
    private function dispatchOrderEmails(array $order, array $items, string $customerSubject, string $ownerSubject): array
    {
        $orderId = (int) $order['id'];
        $customerEmail = $this->resolveCustomerEmail($order);
        $ownerEmail = $this->resolveOwnerEmail();
        $mailer = new Mailer();
        $customerOk = false;
        $ownerOk = false;

        if ($customerEmail !== '') {
            $customerOk = $mailer->send(
                $customerEmail,
                $customerSubject,
                $this->buildInvoiceHtml($order, $items),
                true,
                null,
                $this->buildInvoiceAltBody($order, $items)
            );
            if (!$customerOk) {
                error_log('OrderMailService: customer email failed for order #' . $orderId . ': ' . $mailer->getLastError());
            }
        } else {
            error_log('OrderMailService: missing/invalid customer email for order #' . $orderId);
        }

        if ($ownerEmail !== '') {
            $ownerOk = $mailer->send(
                $ownerEmail,
                $ownerSubject,
                $this->buildOwnerNotificationHtml($order, $items),
                true
            );
            if (!$ownerOk) {
                error_log('OrderMailService: owner email failed for order #' . $orderId . ': ' . $mailer->getLastError());
            }
        } else {
            error_log('OrderMailService: missing/invalid owner email for order #' . $orderId);
        }

        // Only stamp when the customer got the invoice. Owner-only success used to
        // block retries, so the shopper never received order details.
        if ($customerOk || ($customerEmail === '' && $ownerOk)) {
            $this->db->prepare(
                'UPDATE orders SET email_notified_at = NOW(), updated_at = NOW() WHERE id = :id'
            )->execute(['id' => $orderId]);
        }

        return ['customer' => $customerOk, 'owner' => $ownerOk, 'skipped' => false];
    }

    /** @param array<string, mixed> $order */
    private function resolveCustomerEmail(array $order): string
    {
        $candidates = [
            $order['customer_email'] ?? '',
        ];
        foreach (['shipping_address', 'billing_address'] as $key) {
            $addr = $this->decodeAddress($order[$key] ?? null);
            $candidates[] = $addr['email'] ?? '';
        }

        foreach ($candidates as $raw) {
            $email = strtolower(trim((string) $raw));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return '';
    }

    private function loadOrder(int $orderId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
             FROM orders o
             JOIN users u ON u.id = o.user_id
             WHERE o.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function resolveOwnerEmail(): string
    {
        $keys = ['store_email', 'support_email', 'owner_email'];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $this->db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ({$placeholders})");
        $stmt->execute($keys);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['key']] = trim((string) $row['value']);
        }

        foreach ($keys as $key) {
            $val = $map[$key] ?? '';
            if ($val !== '' && filter_var($val, FILTER_VALIDATE_EMAIL)) {
                return $val;
            }
        }

        $envOwner = trim((string) ($_ENV['OWNER_EMAIL'] ?? ''));
        if ($envOwner !== '' && filter_var($envOwner, FILTER_VALIDATE_EMAIL)) {
            return $envOwner;
        }

        $from = trim((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? ''));
        if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }

        $admin = $this->db->query(
            "SELECT email FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1"
        )->fetch();

        return trim((string) ($admin['email'] ?? ''));
    }

    /** @param array<string, mixed> $order @param list<array<string, mixed>> $items */
    private function orderViewUrl(array $order): string
    {
        $app = require dirname(__DIR__) . '/config/app.php';
        $frontend = rtrim((string) ($app['frontend_url'] ?? ''), '/');
        return $frontend !== '' ? $frontend . '/profile?section=orders&order=' . (int) $order['id'] : 'https://vibhaajewellery.in/profile';
    }

    /** @param array<string, mixed> $order @param list<array<string, mixed>> $items */
    private function buildInvoiceAltBody(array $order, array $items): string
    {
        $name = (string) ($order['customer_name'] ?? 'Customer');
        $orderNumber = (string) $order['order_number'];
        $isCod = ($order['payment_method'] ?? '') === 'cod';
        $intro = $isCod
            ? 'Thank you for your order. Please pay the amount below when it is delivered.'
            : 'Thank you for your order. Payment is confirmed. Here are your details.';
        $lines = ["Hi {$name},", '', $intro, '', 'Order: ' . $orderNumber];
        foreach ($items as $item) {
            $title = (string) ($item['product_name'] ?? 'Item');
            $meta = $this->itemOptionsLabel($item);
            $qty = (int) ($item['quantity'] ?? 0);
            $line = $this->money((float) ($item['total'] ?? 0));
            $lines[] = '- ' . $title . ($meta !== '' ? " ({$meta})" : '') . " x {$qty} = {$line}";
        }
        $lines[] = '';
        $lines[] = 'Total: ' . $this->money((float) ($order['total'] ?? 0));
        $shipping = $this->decodeAddress($order['shipping_address'] ?? null);
        $shipText = trim(str_replace(['<br>', '<br/>', '<br />'], ', ', $this->formatAddressHtml($shipping)));
        $shipText = html_entity_decode(strip_tags($shipText), ENT_QUOTES, 'UTF-8');
        if ($shipText !== '' && $shipText !== '—') {
            $lines[] = 'Ship to: ' . $shipText;
        }
        $lines[] = '';
        $lines[] = 'View order: ' . $this->orderViewUrl($order);
        $lines[] = '';
        $lines[] = 'Vibhaa Jewellery';
        $lines[] = 'https://vibhaajewellery.in';
        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $order @param list<array<string, mixed>> $items */
    private function buildInvoiceHtml(array $order, array $items): string
    {
        $name = htmlspecialchars((string) ($order['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
        $orderNumber = htmlspecialchars((string) $order['order_number'], ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars(date('d M Y, h:i A', strtotime((string) $order['created_at'])), ENT_QUOTES, 'UTF-8');
        $paymentMethod = htmlspecialchars(strtoupper((string) ($order['payment_method'] ?? 'N/A')), ENT_QUOTES, 'UTF-8');
        $paymentStatus = htmlspecialchars(strtoupper((string) ($order['payment_status'] ?? '')), ENT_QUOTES, 'UTF-8');
        $isCod = ($order['payment_method'] ?? '') === 'cod';
        $intro = $isCod
            ? 'Thank you for your order. Please pay the amount below when your jewellery is delivered.'
            : 'Thank you for your order. Payment is confirmed. Here are your order details.';

        $itemRows = '';
        foreach ($items as $item) {
            $title = htmlspecialchars((string) ($item['product_name'] ?? 'Item'), ENT_QUOTES, 'UTF-8');
            $meta = $this->itemOptionsLabel($item);
            $metaHtml = $meta !== ''
                ? ' <span style="color:#555555;">(' . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . ')</span>'
                : '';
            $qty = (int) ($item['quantity'] ?? 0);
            $line = $this->money((float) ($item['total'] ?? 0));
            $itemRows .= "<p style=\"margin:0 0 8px;font-size:15px;\">{$title}{$metaHtml} × {$qty} — {$line}</p>";
        }

        $total = $this->money((float) ($order['total'] ?? 0));
        $shipping = $this->decodeAddress($order['shipping_address'] ?? null);
        $shipHtml = $this->formatAddressHtml($shipping);
        $ordersUrl = htmlspecialchars($this->orderViewUrl($order), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Order {$orderNumber}</title></head>
<body style="margin:0;padding:24px;font-family:Arial,Helvetica,sans-serif;color:#111111;background:#ffffff;">
  <p style="margin:0 0 12px;font-size:16px;">Hi {$name},</p>
  <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">{$intro}</p>
  <p style="margin:0 0 8px;font-size:15px;"><strong>Order:</strong> {$orderNumber}</p>
  <p style="margin:0 0 8px;font-size:15px;"><strong>Date:</strong> {$date}</p>
  <p style="margin:0 0 16px;font-size:15px;"><strong>Payment:</strong> {$paymentMethod} · {$paymentStatus}</p>
  {$itemRows}
  <p style="margin:16px 0;font-size:16px;"><strong>Total: {$total}</strong></p>
  <p style="margin:0 0 8px;font-size:14px;color:#555555;"><strong>Ship to</strong></p>
  <p style="margin:0 0 16px;font-size:14px;line-height:1.5;color:#333333;">{$shipHtml}</p>
  <p style="margin:0 0 12px;font-size:15px;"><a href="{$ordersUrl}" style="color:#005155;">View your order</a></p>
  <p style="margin:24px 0 0;font-size:13px;color:#555555;">Vibhaa Jewellery<br>https://vibhaajewellery.in</p>
</body>
</html>
HTML;
    }

    /** @param array<string, mixed> $order @param list<array<string, mixed>> $items */
    private function buildOwnerNotificationHtml(array $order, array $items): string
    {
        $orderNumber = htmlspecialchars((string) $order['order_number'], ENT_QUOTES, 'UTF-8');
        $customer = htmlspecialchars((string) ($order['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars((string) ($order['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars((string) ($order['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $total = $this->money((float) ($order['total'] ?? 0));
        $date = htmlspecialchars(date('d M Y, h:i A', strtotime((string) $order['created_at'])), ENT_QUOTES, 'UTF-8');
        $paymentMethod = htmlspecialchars(strtoupper((string) ($order['payment_method'] ?? 'N/A')), ENT_QUOTES, 'UTF-8');
        $itemCount = count($items);

        $itemLines = '';
        foreach ($items as $item) {
            $title = htmlspecialchars((string) ($item['product_name'] ?? 'Item'), ENT_QUOTES, 'UTF-8');
            $meta = $this->itemOptionsLabel($item);
            $metaHtml = $meta !== ''
                ? ' <span style="color:#888;">(' . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . ')</span>'
                : '';
            $qty = (int) ($item['quantity'] ?? 0);
            $itemLines .= "<li style=\"margin:0 0 6px;\">{$title}{$metaHtml} × {$qty}</li>";
        }

        $shipping = $this->decodeAddress($order['shipping_address'] ?? null);
        $shipHtml = $this->formatAddressHtml($shipping);

        $app = require dirname(__DIR__) . '/config/app.php';
        $adminUrl = rtrim((string) ($_ENV['ADMIN_URL'] ?? ($app['frontend_url'] ?? '')), '/');

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>New order {$orderNumber}</title></head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
  <div style="max-width:560px;margin:24px auto;background:#ffffff;border:1px solid #e8e8e8;">
    <div style="padding:24px 28px;border-bottom:2px solid #111;">
      <div style="font-size:18px;font-weight:700;letter-spacing:0.08em;">Vibhaa Jewellery</div>
      <div style="margin-top:6px;font-size:14px;color:#666;">New order notification</div>
    </div>
    <div style="padding:24px 28px;font-size:14px;line-height:1.5;">
      <p style="margin:0 0 16px;">A new paid order has been placed.</p>
      <table style="width:100%;font-size:13px;margin-bottom:16px;">
        <tr><td style="padding:4px 0;color:#666;">Order</td><td style="padding:4px 0;text-align:right;font-weight:600;">#{$orderNumber}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Date</td><td style="padding:4px 0;text-align:right;">{$date}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Customer</td><td style="padding:4px 0;text-align:right;">{$customer}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Email</td><td style="padding:4px 0;text-align:right;">{$email}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Phone</td><td style="padding:4px 0;text-align:right;">{$phone}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Payment</td><td style="padding:4px 0;text-align:right;">{$paymentMethod} · PAID</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Items</td><td style="padding:4px 0;text-align:right;">{$itemCount}</td></tr>
        <tr><td style="padding:8px 0 0;font-weight:700;border-top:1px solid #eee;">Total</td><td style="padding:8px 0 0;text-align:right;font-weight:700;border-top:1px solid #eee;">{$total}</td></tr>
      </table>
      <div style="margin-bottom:16px;">
        <div style="font-size:12px;letter-spacing:0.08em;text-transform:uppercase;font-weight:600;margin-bottom:8px;">Items</div>
        <ul style="margin:0;padding-left:18px;">{$itemLines}</ul>
      </div>
      <div>
        <div style="font-size:12px;letter-spacing:0.08em;text-transform:uppercase;font-weight:600;margin-bottom:8px;">Ship to</div>
        <div style="color:#444;">{$shipHtml}</div>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private function decodeAddress(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function formatAddressHtml(array $addr): string
    {
        if ($addr === []) {
            return '—';
        }
        $parts = [
            $addr['name'] ?? $addr['full_name'] ?? '',
            $addr['address_line1'] ?? '',
            $addr['address_line2'] ?? '',
            trim(($addr['city'] ?? '') . ', ' . ($addr['state'] ?? '') . ' ' . ($addr['pincode'] ?? '')),
            !empty($addr['phone']) ? 'Phone: ' . $addr['phone'] : '',
        ];
        $parts = array_values(array_filter(array_map(static fn ($p) => trim((string) $p), $parts), static fn ($p) => $p !== '' && $p !== ','));
        $safe = array_map(static fn ($p) => htmlspecialchars($p, ENT_QUOTES, 'UTF-8'), $parts);
        return $safe === [] ? '—' : implode('<br>', $safe);
    }

    /** @param array<string, mixed> $item */
    private function itemOptionsLabel(array $item): string
    {
        $bits = [];
        $color = trim((string) ($item['color'] ?? ''));
        $size = trim((string) ($item['size'] ?? ''));
        if ($color !== '') {
            $bits[] = 'Color: ' . $color;
        }
        if ($size !== '') {
            $bits[] = 'Size: ' . $size;
        }
        return implode(' · ', $bits);
    }

    private function money(float $amount): string
    {
        return '₹' . number_format($amount, 2);
    }

    /**
     * Email the customer when admin updates order status.
     *
     * @return array{sent: bool, message: string}
     */
    public function notifyStatusUpdate(int $orderId, string $status): array
    {
        $order = $this->loadOrder($orderId);
        if (!$order) {
            return ['sent' => false, 'message' => 'Order not found.'];
        }

        $customerEmail = trim((string) ($order['customer_email'] ?? ''));
        if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            return ['sent' => false, 'message' => 'Customer email is missing or invalid.'];
        }

        $labels = [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'packed' => 'Packed',
            'shipped' => 'Shipped',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'returned' => 'Returned',
            'refunded' => 'Refunded',
        ];
        $statusLabel = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
        $messages = [
            'pending' => 'Your order is pending confirmation.',
            'confirmed' => 'Your order has been confirmed and will be prepared soon.',
            'processing' => 'We are now processing your order.',
            'packed' => 'Your order has been packed and is ready to ship.',
            'shipped' => 'Your order has been shipped.',
            'out_for_delivery' => 'Your order is out for delivery.',
            'delivered' => 'Your order has been delivered. We hope you love it!',
            'cancelled' => 'Your order has been cancelled.',
            'returned' => 'Your return request has been recorded.',
            'refunded' => 'Your refund has been processed.',
        ];
        $bodyText = $messages[$status] ?? ('Your order status is now: ' . $statusLabel);

        $name = htmlspecialchars((string) ($order['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
        $orderNumber = htmlspecialchars((string) $order['order_number'], ENT_QUOTES, 'UTF-8');
        $statusSafe = htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8');
        $bodySafe = htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8');

        $app = require dirname(__DIR__) . '/config/app.php';
        $frontend = rtrim((string) ($app['frontend_url'] ?? ''), '/');
        $ordersUrl = $frontend !== ''
            ? $frontend . '/profile?section=orders&order=' . (int) $order['id']
            : '#';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Order update {$orderNumber}</title></head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
  <div style="max-width:560px;margin:24px auto;background:#ffffff;border:1px solid #e8e8e8;">
    <div style="padding:24px 28px;border-bottom:2px solid #111;">
      <div style="font-size:18px;font-weight:700;letter-spacing:0.08em;">Vibhaa Jewellery</div>
      <div style="margin-top:6px;font-size:14px;color:#666;">Order status update</div>
    </div>
    <div style="padding:24px 28px;font-size:14px;line-height:1.5;">
      <p style="margin:0 0 12px;">Hi {$name},</p>
      <p style="margin:0 0 16px;color:#444;">{$bodySafe}</p>
      <table style="width:100%;font-size:13px;margin-bottom:20px;">
        <tr><td style="padding:4px 0;color:#666;">Order</td><td style="padding:4px 0;text-align:right;font-weight:600;">#{$orderNumber}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Status</td><td style="padding:4px 0;text-align:right;font-weight:600;">{$statusSafe}</td></tr>
      </table>
      <p style="margin:0;">
        <a href="{$ordersUrl}" style="display:inline-block;background:#111;color:#fff;text-decoration:none;padding:12px 18px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;">View order</a>
      </p>
    </div>
  </div>
</body>
</html>
HTML;

        $mailer = new Mailer();
        $sent = $mailer->send(
            $customerEmail,
            'Vibhaa Jewellery order update — #' . $order['order_number'] . ' is ' . $statusLabel,
            $html,
            true
        );

        return [
            'sent' => $sent,
            'message' => $sent ? 'Status email sent to customer.' : 'Failed to send status email.',
        ];
    }

    /**
     * Email customer about return request decision (in_process / rejected / notes).
     *
     * @return array{sent: bool, message: string}
     */
    public function notifyReturnUpdate(int $orderId, string $returnStatus, string $adminNotes = ''): array
    {
        $order = $this->loadOrder($orderId);
        if (!$order) {
            return ['sent' => false, 'message' => 'Order not found.'];
        }

        $customerEmail = trim((string) ($order['customer_email'] ?? ''));
        if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            return ['sent' => false, 'message' => 'Customer email is missing or invalid.'];
        }

        $labels = [
            'in_process' => 'In process',
            'rejected' => 'Not approved',
            'completed' => 'Completed',
            'requested' => 'Requested',
        ];
        $bodies = [
            'in_process' => 'Your return request is being processed by our team.',
            'rejected' => 'Your return request was not approved. If you have questions, reply to this email or contact support.',
            'completed' => 'Your return has been completed.',
            'requested' => 'We received your return request.',
        ];
        $statusLabel = $labels[$returnStatus] ?? ucfirst(str_replace('_', ' ', $returnStatus));
        $bodyText = $bodies[$returnStatus] ?? ('Your return status is now: ' . $statusLabel);

        $name = htmlspecialchars((string) ($order['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
        $orderNumber = htmlspecialchars((string) $order['order_number'], ENT_QUOTES, 'UTF-8');
        $statusSafe = htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8');
        $bodySafe = htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8');
        $notesBlock = '';
        if (trim($adminNotes) !== '') {
            $notesSafe = htmlspecialchars(trim($adminNotes), ENT_QUOTES, 'UTF-8');
            $notesBlock = '<p style="margin:16px 0 0;padding:12px;background:#f6f6f6;font-size:13px;"><strong>Note from Vibhaa Jewellery:</strong><br>'
                . nl2br($notesSafe) . '</p>';
        }

        $app = require dirname(__DIR__) . '/config/app.php';
        $frontend = rtrim((string) ($app['frontend_url'] ?? ''), '/');
        $profileUrl = $frontend !== ''
            ? $frontend . '/profile?section=orders&order=' . (int) $order['id']
            : '#';
        $profileHref = htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Return update {$orderNumber}</title></head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
  <div style="max-width:560px;margin:24px auto;background:#ffffff;border:1px solid #e8e8e8;">
    <div style="padding:24px 28px;border-bottom:2px solid #111;">
      <div style="font-size:18px;font-weight:700;letter-spacing:0.08em;">Vibhaa Jewellery</div>
      <div style="margin-top:6px;font-size:14px;color:#666;">Return update</div>
    </div>
    <div style="padding:24px 28px;font-size:14px;line-height:1.5;">
      <p style="margin:0 0 12px;">Hi {$name},</p>
      <p style="margin:0 0 16px;color:#444;">{$bodySafe}</p>
      <table style="width:100%;font-size:13px;margin-bottom:12px;">
        <tr><td style="padding:4px 0;color:#666;">Order</td><td style="padding:4px 0;text-align:right;font-weight:600;">#{$orderNumber}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Return status</td><td style="padding:4px 0;text-align:right;font-weight:600;">{$statusSafe}</td></tr>
      </table>
      {$notesBlock}
      <p style="margin:20px 0 0;">
        <a href="{$profileHref}" style="display:inline-block;background:#111;color:#fff;text-decoration:none;padding:12px 18px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;">View order</a>
      </p>
    </div>
  </div>
</body>
</html>
HTML;

        $mailer = new Mailer();
        $sent = $mailer->send(
            $customerEmail,
            'Vibhaa Jewellery return update — Order #' . $order['order_number'],
            $html,
            true
        );

        return [
            'sent' => $sent,
            'message' => $sent ? 'Return email sent to customer.' : 'Failed to send return email.',
        ];
    }

    /**
     * Email customer with tracking number + track link.
     *
     * @return array{sent: bool, message: string, track_url: ?string}
     */
    public function notifyTrackingShared(int $orderId, string $trackingNumber, string $carrier = ''): array
    {
        $order = $this->loadOrder($orderId);
        if (!$order) {
            return ['sent' => false, 'message' => 'Order not found.', 'track_url' => null];
        }

        $customerEmail = trim((string) ($order['customer_email'] ?? ''));
        if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            return ['sent' => false, 'message' => 'Customer email is missing or invalid.', 'track_url' => null];
        }

        $app = require dirname(__DIR__) . '/config/app.php';
        $frontend = rtrim((string) ($app['frontend_url'] ?? ''), '/');
        $trackUrl = $frontend !== ''
            ? $frontend . '/track-order?order=' . rawurlencode((string) $order['order_number'])
                . '&email=' . rawurlencode($customerEmail)
            : null;
        $profileUrl = $frontend !== ''
            ? $frontend . '/profile?section=orders&order=' . (int) $order['id']
            : '#';

        $name = htmlspecialchars((string) ($order['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
        $orderNumber = htmlspecialchars((string) $order['order_number'], ENT_QUOTES, 'UTF-8');
        $trackingSafe = htmlspecialchars($trackingNumber, ENT_QUOTES, 'UTF-8');
        $carrierSafe = htmlspecialchars($carrier !== '' ? $carrier : 'Courier', ENT_QUOTES, 'UTF-8');
        $trackHref = htmlspecialchars((string) ($trackUrl ?: $profileUrl), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Tracking for {$orderNumber}</title></head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
  <div style="max-width:560px;margin:24px auto;background:#ffffff;border:1px solid #e8e8e8;">
    <div style="padding:24px 28px;border-bottom:2px solid #111;">
      <div style="font-size:18px;font-weight:700;letter-spacing:0.08em;">Vibhaa Jewellery</div>
      <div style="margin-top:6px;font-size:14px;color:#666;">Your order is on the way</div>
    </div>
    <div style="padding:24px 28px;font-size:14px;line-height:1.5;">
      <p style="margin:0 0 12px;">Hi {$name},</p>
      <p style="margin:0 0 16px;color:#444;">
        Tracking details for your order are ready. Use the link below to track your shipment.
      </p>
      <table style="width:100%;font-size:13px;margin-bottom:20px;">
        <tr><td style="padding:4px 0;color:#666;">Order</td><td style="padding:4px 0;text-align:right;font-weight:600;">#{$orderNumber}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Carrier</td><td style="padding:4px 0;text-align:right;">{$carrierSafe}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Tracking #</td><td style="padding:4px 0;text-align:right;font-weight:600;">{$trackingSafe}</td></tr>
      </table>
      <p style="margin:0 0 12px;">
        <a href="{$trackHref}" style="display:inline-block;background:#111;color:#fff;text-decoration:none;padding:12px 18px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;">Track order</a>
      </p>
      <p style="margin:0;font-size:12px;color:#888;">Or open your order in your profile to view updates.</p>
    </div>
  </div>
</body>
</html>
HTML;

        $mailer = new Mailer();
        $sent = $mailer->send(
            $customerEmail,
            'Vibhaa Jewellery tracking — Order #' . $order['order_number'],
            $html,
            true
        );

        return [
            'sent' => $sent,
            'message' => $sent ? 'Tracking email sent to customer.' : 'Failed to send tracking email.',
            'track_url' => $trackUrl,
        ];
    }

    /** Notify store owner that a customer raised a tracking follow-up. */
    public function notifyAdminTrackingFollowup(int $followupId): array
    {
        $stmt = $this->db->prepare(
            'SELECT f.*, o.order_number, o.status AS order_status, o.total AS order_total
             FROM tracking_followups f
             JOIN orders o ON o.id = f.order_id
             WHERE f.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $followupId]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['sent' => false, 'message' => 'Follow-up not found.'];
        }

        $ownerEmail = $this->resolveOwnerEmail();
        if ($ownerEmail === '' || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            return ['sent' => false, 'message' => 'Owner email is not configured.'];
        }

        $app = require dirname(__DIR__) . '/config/app.php';
        $adminUrl = rtrim((string) ($_ENV['ADMIN_URL'] ?? ''), '/');
        $followupsUrl = $adminUrl !== '' ? $adminUrl . '/followups' : '#';

        $orderNumber = htmlspecialchars((string) $row['order_number'], ENT_QUOTES, 'UTF-8');
        $customer = htmlspecialchars((string) ($row['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars((string) ($row['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars((string) ($row['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $subject = htmlspecialchars((string) $row['subject'], ENT_QUOTES, 'UTF-8');
        $message = nl2br(htmlspecialchars((string) $row['message'], ENT_QUOTES, 'UTF-8'));
        $status = htmlspecialchars((string) ($row['order_status'] ?? ''), ENT_QUOTES, 'UTF-8');
        $total = $this->money((float) ($row['order_total'] ?? 0));
        $adminHref = htmlspecialchars($followupsUrl, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Tracking query {$orderNumber}</title></head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
  <div style="max-width:560px;margin:24px auto;background:#ffffff;border:1px solid #e8e8e8;">
    <div style="padding:24px 28px;border-bottom:2px solid #111;">
      <div style="font-size:18px;font-weight:700;letter-spacing:0.08em;">Vibhaa Jewellery</div>
      <div style="margin-top:6px;font-size:14px;color:#666;">New tracking follow-up</div>
    </div>
    <div style="padding:24px 28px;font-size:14px;line-height:1.5;">
      <p style="margin:0 0 16px;">A customer requested tracking details for an order.</p>
      <table style="width:100%;font-size:13px;margin-bottom:16px;">
        <tr><td style="padding:4px 0;color:#666;">Order</td><td style="padding:4px 0;text-align:right;font-weight:600;">#{$orderNumber}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Order status</td><td style="padding:4px 0;text-align:right;">{$status}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Total</td><td style="padding:4px 0;text-align:right;">{$total}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Customer</td><td style="padding:4px 0;text-align:right;">{$customer}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Email</td><td style="padding:4px 0;text-align:right;">{$email}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Phone</td><td style="padding:4px 0;text-align:right;">{$phone}</td></tr>
        <tr><td style="padding:4px 0;color:#666;">Subject</td><td style="padding:4px 0;text-align:right;">{$subject}</td></tr>
      </table>
      <div style="margin-bottom:20px;">
        <div style="font-size:12px;letter-spacing:0.08em;text-transform:uppercase;font-weight:600;margin-bottom:8px;">Message</div>
        <div style="color:#444;">{$message}</div>
      </div>
      <p style="margin:0;">
        <a href="{$adminHref}" style="display:inline-block;background:#111;color:#fff;text-decoration:none;padding:12px 18px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;">Open follow-ups</a>
      </p>
    </div>
  </div>
</body>
</html>
HTML;

        $mailer = new Mailer();
        $sent = $mailer->send(
            $ownerEmail,
            'Vibhaa Jewellery tracking query — Order #' . $row['order_number'],
            $html,
            true
        );

        return [
            'sent' => $sent,
            'message' => $sent ? 'Admin notified.' : 'Failed to email admin.',
        ];
    }
}
