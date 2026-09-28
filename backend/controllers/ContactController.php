<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';

final class ContactController extends BaseController
{
    public function store(array $params = []): void
    {
        $input = $this->getJsonInput();

        $validator = Validator::make($input)
            ->required('name')
            ->required('email')
            ->email('email')
            ->required('subject')
            ->required('message');

        if ($validator->fails()) {
            Response::jsonError('Validation failed.', 422, $validator->errors());
        }

        $stmt = $this->db->prepare(
            'INSERT INTO contact_messages (name, email, phone, subject, message, status, created_at)
             VALUES (:name, :email, :phone, :subject, :message, :status, NOW())'
        );
        $stmt->execute([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'subject' => $input['subject'],
            'message' => $input['message'],
            'status' => 'new',
        ]);

        $mailer = new Mailer();
        $appConfig = require dirname(__DIR__) . '/config/app.php';
        $inbox = trim((string) ($_ENV['OWNER_EMAIL'] ?? ''));
        if ($inbox === '' || !filter_var($inbox, FILTER_VALIDATE_EMAIL)) {
            $inbox = (string) ($appConfig['mail']['from_address'] ?? '');
        }

        $safeName = htmlspecialchars((string) $input['name'], ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars((string) $input['email'], ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars((string) $input['subject'], ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars((string) $input['message'], ENT_QUOTES, 'UTF-8'));

        if ($inbox !== '' && filter_var($inbox, FILTER_VALIDATE_EMAIL)) {
            $sent = $mailer->send(
                $inbox,
                'New Contact: ' . $input['subject'],
                "<p>From: {$safeName} ({$safeEmail})</p><p>Subject: {$safeSubject}</p><p>{$safeMessage}</p>",
                true,
                (string) $input['email']
            );
            if (!$sent) {
                error_log('Contact notification email failed: ' . $mailer->getLastError());
            }
        }

        Response::jsonSuccess(null, 'Message sent successfully.', 201);
    }
}
