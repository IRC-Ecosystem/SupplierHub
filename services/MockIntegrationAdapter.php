<?php
class MockIntegrationAdapter {
    public static function publish(array $event): array {
        $fail = (string) (getenv('LOCAL_MOCK_FAIL_EVENT') ?: '');
        if ($fail !== '' && $fail === $event['event_type']) {
            return ['status' => 'error', 'message' => 'Mock adapter configured to fail this event.'];
        }

        return [
            'status' => 'success',
            'message' => 'Event accepted by local mock adapter.',
            'reference' => 'MOCK-' . strtoupper(substr(hash('sha256', $event['event_id']), 0, 16)),
        ];
    }
}
