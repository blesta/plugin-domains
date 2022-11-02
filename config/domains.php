<?php
Configure::set('Domains.first_reminder_days_min', 26);
Configure::set('Domains.first_reminder_days_max', 35);
Configure::set('Domains.second_reminder_days_min', 4);
Configure::set('Domains.second_reminder_days_max', 10);
Configure::set('Domains.expiry_notice_days_min', 1);
Configure::set('Domains.expiry_notice_days_max', 5);
Configure::set(
    'Domains.install.emails',
    [
        [
            'action' => 'Domains.domain_renewal_1',
            'type' => 'client',
            'plugin_dir' => 'domains',
            'tags' => '{domain},{service},{contact},{client_uri}',
            'from' => 'sales@mydomain.com',
            'from_name' => 'Domain Manager',
            'subject' => '{domain} will expire on {service.expiration_date}',
            'text' => 'Hi {contact.first_name},

This is a reminder that the domain {domain} will expire on {service.expiration_date}.

To renew this domain, please log in at: {client_uri}.
Failure to renew will result in loss of domain ownership.

Thank you for your continued business!',
            'html' => '<p>Hi {contact.first_name},</p>

<p>This is a reminder that the domain {domain} will expire on {service.expiration_date}.</p>

<p>To renew this domain, please log in at: {client_uri}.<br/>
Failure to renew will result in loss of domain ownership.</p>

<p>Thank you for your continued business!</p>'
        ],
        [
            'action' => 'Domains.domain_renewal_2',
            'type' => 'client',
            'plugin_dir' => 'domains',
            'tags' => '{domain},{service},{contact},{client_uri}',
            'from' => 'sales@mydomain.com',
            'from_name' => 'Domain Manager',
            'subject' => '{domain} will expire on {service.expiration_date} - SECOND NOTICE',
            'text' => 'Hi {contact.first_name},

This is a reminder that the domain {domain} will expire on {service.expiration_date}.

To renew this domain, please log in at: {client_uri}.
Failure to renew will result in loss of domain ownership.

Thank you for your continued business!',
            'html' => '<p>Hi {contact.first_name},</p>

<p>This is a reminder that the domain {domain} will expire on {service.expiration_date}.</p>

<p>To renew this domain, please log in at: {client_uri}.<br/>
Failure to renew will result in loss of domain ownership.</p>

<p>Thank you for your continued business!</p>'
        ],
        [
            'action' => 'Domains.domain_expiration',
            'type' => 'client',
            'plugin_dir' => 'domains',
            'tags' => '{domain},{service},{contact},{client_uri}',
            'from' => 'sales@mydomain.com',
            'from_name' => 'Domain Manager',
            'subject' => '{domain} expired on {service.expiration_date}',
            'text' => 'Hi {contact.first_name},

This is a notice that the domain {domain} has expired on {service.expiration_date} and is no longer under your ownership.
To re-purchase this domain, please log in at: {client_uri}.

If you believe this expiration is in error, please contact us.',
            'html' => '<p>Hi {contact.first_name},</p>

<p>This is a notice that the domain {domain} has expired on {service.expiration_date} and is no longer under your ownership.<br/>
To re-purchase this domain, please log in at: {client_uri}.</p>

<p>If you believe this expiration is in error, please contact us.</p>'
        ]
    ]
);
