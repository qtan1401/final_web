<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #2563eb; color: white; padding: 20px; border-radius: 12px 12px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 1.5rem;">Note Manager</h1>
    </div>
    <div style="background: #ffffff; padding: 30px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 12px 12px;">
        <h2 style="color: #1e293b; margin-top: 0;">A note has been shared with you!</h2>
        <p style="color: #475569; line-height: 1.6;">
            <strong>{{ $sharedBy->display_name ?? $sharedBy->email }}</strong> has shared the note
            "<strong>{{ $note->title }}</strong>" with you.
        </p>
        <div style="background: #f1f5f9; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                <strong>Permission:</strong> {{ $permission === 'edit' ? 'Can Edit' : 'View Only' }}
            </p>
        </div>
        <a href="{{ url('/frontend/dashboard.html') }}"
           style="display: inline-block; background: #2563eb; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-top: 10px;">
            Open Note Manager
        </a>
        <p style="color: #94a3b8; font-size: 0.85rem; margin-top: 20px;">
            If the button doesn't work, copy this link: {{ url('/frontend/dashboard.html') }}
        </p>
    </div>
</div>
