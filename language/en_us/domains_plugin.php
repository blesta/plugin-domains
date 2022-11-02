<?php
/**
 * en_us language for the Domain Manager plugin.
 */
// Basics
$lang['DomainsPlugin.name'] = 'Domain Manager';
$lang['DomainsPlugin.description'] = 'Used to handle various aspects of domain management and sales. This plugin is currently in beta phase.';

// Errors
$lang['DomainsPlugin.!error.module_id.exists'] = 'Invalid module ID.';

// Cron Tasks
$lang['DomainsPlugin.getCronTasks.domain_synchronization'] = 'Domain Synchronization';
$lang['DomainsPlugin.getCronTasks.domain_synchronization_description'] = 'Synchronize domain services with the expiry date from their registrar module';
$lang['DomainsPlugin.getCronTasks.domain_tld_synchronization'] = 'Domain TLD Synchronization';
$lang['DomainsPlugin.getCronTasks.domain_tld_synchronization_description'] = 'Synchronize packages with the TLD pricing from their registrar module';
$lang['DomainsPlugin.getCronTasks.domain_term_change'] = 'Change Domain Term';
$lang['DomainsPlugin.getCronTasks.domain_term_change_description'] = 'Change services with a term longer than a year to a yearly term';
$lang['DomainsPlugin.getCronTasks.domain_renewal_reminders'] = 'Send Expiration Reminders';
$lang['DomainsPlugin.getCronTasks.domain_renewal_reminders_description'] = 'Send email reminders for domains that are drawing close to their expiration date';

// Plugin Actions
$lang['DomainsPlugin.nav_secondary_staff.domain_options'] = 'Domain Options';
$lang['DomainsPlugin.nav_secondary_staff.domains'] = 'Domains';

$lang['DomainsPlugin.nav_client.services'] = 'Services';
$lang['DomainsPlugin.nav_client.domains'] = 'Domains';

// Plugin Cards
$lang['DomainsPlugin.card_client.getDomainCount'] = 'Domains';

// Permission Groups
$lang['DomainsPlugin.permission.admin_domains'] = 'Domains';
$lang['DomainsPlugin.permission.admin_domains.browse'] = 'Domains';
$lang['DomainsPlugin.permission.admin_domains.tlds'] = 'TLD Pricing';
$lang['DomainsPlugin.permission.admin_domains.registrars'] = 'Registrars';
$lang['DomainsPlugin.permission.admin_domains.whois'] = 'Whois';
$lang['DomainsPlugin.permission.admin_domains.configuration'] = 'Configuration';

// TLD Package Group Details
$lang['DomainsPlugin.tld_package_group.name'] = 'TLDs Pricing Packages';
$lang['DomainsPlugin.tld_package_group.description'] = 'A package group for hiding and managing all the TLD pricing packages';

// TLD Addons
$lang['DomainsPlugin.email_forwarding.name'] = 'Email Forwarding';
$lang['DomainsPlugin.email_forwarding.description'] = 'Email Forwarding';
$lang['DomainsPlugin.dns_management.name'] = 'DNS Management';
$lang['DomainsPlugin.dns_management.description'] = 'DNS Management';
$lang['DomainsPlugin.id_protection.name'] = 'ID Protection';
$lang['DomainsPlugin.id_protection.description'] = 'ID Protection';
$lang['DomainsPlugin.enabled'] = 'Enabled';

// Staff Widget
$lang['DomainsPlugin.widget_staff_home.main'] = 'Domains';

// Client Widget
$lang['DomainsPlugin.widget_client_home.main'] = 'Domains';

// Upgrade Tasks
$lang['DomainsPlugin.upgrade.domains_dns_management_option_group'] = 'Manage the DNS zone for your domain name through name servers we provide. Create and edit records such as A, CNAME, MX, TXT, and more for your domain.';
$lang['DomainsPlugin.upgrade.domains_email_forwarding_option_group'] = 'Create email addresses at your domain that forward to an external email address.';
$lang['DomainsPlugin.upgrade.domains_id_protection_option_group'] = 'All domain names must have valid contact information as part of ICANN rules. This option will hide these details from the public whois, keeping your information private.';
