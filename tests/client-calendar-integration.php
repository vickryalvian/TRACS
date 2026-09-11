<?php
declare(strict_types=1);

require_once __DIR__.'/../modules/client-portfolio/controller.php';
require_once __DIR__.'/../modules/calendar/CalendarService.php';

function client_check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function client_reject(callable $operation): void {
    try { $operation(); } catch (Throwable $error) { return; }
    throw new RuntimeException('Expected operation to be rejected.');
}

// Only an isolated, freshly named database is mutated; no application data is copied.
$db = new mysqli(getenv('TRACS_TEST_DB_HOST') ?: '127.0.0.1', getenv('TRACS_TEST_DB_USER') ?: 'root', getenv('TRACS_TEST_DB_PASS') ?: 'root_secret', '', (int)(getenv('TRACS_TEST_DB_PORT') ?: 3307));
$name = 'tracs_client_calendar_test_'.bin2hex(random_bytes(5));
$db->query("CREATE DATABASE `$name`");
$db->select_db($name);
$db->set_charset('utf8mb4');
try {
    foreach ($db->query("SHOW FULL TABLES FROM tracs_db WHERE Table_type='BASE TABLE'") as $table) {
        $table = array_values($table)[0];
        $db->query("CREATE TABLE `$table` LIKE tracs_db.`$table`");
    }
    foreach (['tracs_roles','tracs_permissions','tracs_role_permissions'] as $table) $db->query("INSERT INTO `$table` SELECT * FROM tracs_db.`$table`");
    $db->query("INSERT INTO tracs_users (id,email,password,name,role_id) VALUES (1,'client-test@example.test','unused','Test Owner',(SELECT id FROM tracs_roles WHERE slug='agent')), (2,'other-test@example.test','unused','Other Owner',(SELECT id FROM tracs_roles WHERE slug='agent')), (3,'admin-test@example.test','unused','Test Admin',(SELECT id FROM tracs_roles WHERE slug='super_admin'))");
    $migration = file_get_contents(__DIR__.'/../config/migrations/2026_09_10_client_calendar.sql');
    $db->query($migration);
    $db->query($migration);
    $owner = new ClientPortfolioController($db, 1);
    $other = new ClientPortfolioController($db, 2);
    $client = $owner->create(['company_name'=>'Test Client','contact_name'=>'Test PIC','service_name'=>'VPS','service_type'=>'VPS','renewal_date'=>'2026-09-28','invoice_date'=>'2026-09-15','amount'=>'100000','tax_invoice_required'=>true,'tax_invoice_due_date'=>'2026-09-20'], 'Test Owner');
    $detail = $owner->detail($client);
    client_check(count($detail['services']) === 1 && count($detail['billing']) === 1 && count($detail['followups']) === 3, 'Grouped creation or scheduled reminders failed.');
    $id = $owner->addFollowup($client, ['title'=>'Send quotation','action_type'=>'quotation','due_at'=>'2026-09-17T09:00'], 'Test Owner');
    $calendar = new CalendarService($db, 1, ['role_slug'=>'agent']);
    $getEvents = fn() => array_values(array_filter($calendar->getEvents('2026-09-01','2026-09-30')['events'], fn($e) => $e['source']==='clients'));
    $events = $getEvents();
    client_check(count($events)===4 && count(array_unique(array_column($events,'id')))===4, 'Calendar must contain each client activity exactly once.');
    $event = array_values(array_filter($events, fn($e) => $e['source_id']===$id))[0];
    client_check($event['client_id']===$client && $event['meta']['activity_type']==='quotation', 'Structured context missing.');
    $rid = (int)$event['meta']['reminder_id'];
    $db->query("UPDATE tracs_reminders SET title='Updated quotation',due_date='2026-09-19 10:30:00',is_completed=1 WHERE id=$rid");
    $followup = array_values(array_filter($owner->detail($client)['followups'], fn($f)=>(int)$f['id']===$id))[0];
    client_check($followup['title']==='Updated quotation' && $followup['due_at']==='2026-09-19 10:30:00' && $followup['status']==='completed', 'Reminder updates did not reach Clients.');
    $event = array_values(array_filter($getEvents(), fn($e) => $e['source_id']===$id))[0];
    client_check($event['status']==='done' && $event['date']==='2026-09-19', 'Reminder updates did not reach Calendar.');
    $owner->updateFollowup($id, ['title'=>'Reopened quotation','due_at'=>'2026-09-21T08:00','status'=>'open'], 'Test Owner');
    $event = array_values(array_filter($getEvents(), fn($e) => $e['source_id']===$id))[0];
    client_check($event['date']==='2026-09-21' && $event['status']!=='done' && str_contains($event['title'],'Reopened quotation'), 'Clients edits did not reach Calendar.');
    $owner->completeFollowup($id, 'Test Owner');
    client_check((int)$db->query("SELECT is_completed FROM tracs_reminders WHERE id=$rid")->fetch_row()[0]===1, 'Completion did not update canonical reminder.');
    client_reject(fn() => $other->completeFollowup($id, 'Other Owner'));
    client_reject(fn() => $other->updateFollowup($id, ['status'=>'open'], 'Other Owner'));
    client_check($other->detail($client)===null, 'Other owner could read client.');
    $otherEvents = (new CalendarService($db,2,['role_slug'=>'agent']))->getEvents('2026-09-01','2026-09-30')['events'];
    client_check(!array_filter($otherEvents,fn($e)=>isset($e['client_id'])), 'Client activity leaked through Calendar.');
    $allEvents = (new CalendarService($db,3,['role_slug'=>'super_admin']))->getEvents('2026-09-01','2026-09-30')['events'];
    client_check(count(array_filter($allEvents,fn($e)=>($e['meta']['reminder_id']??null)===$rid))===1, 'Admin sees duplicate linked reminders.');
    $before = (int)$db->query('SELECT COUNT(*) FROM tracs_clients')->fetch_row()[0];
    client_reject(fn() => $owner->create(['company_name'=>'Rollback fixture','service_name'=>'Broken','renewal_date'=>'2026-02-31'], 'Test Owner'));
    client_check((int)$db->query('SELECT COUNT(*) FROM tracs_clients')->fetch_row()[0]===$before, 'Grouped creation must roll back on validation failure.');
    $before = (int)$db->query('SELECT COUNT(*) FROM tracs_reminders')->fetch_row()[0];
    client_reject(fn() => $owner->addFollowup($client,['title'=>'Bad date','due_at'=>'2026-02-31T09:00'],'Test Owner'));
    client_reject(fn() => $owner->addFollowup($client,['title'=>'No date'],'Test Owner'));
    client_check((int)$db->query('SELECT COUNT(*) FROM tracs_reminders')->fetch_row()[0]===$before, 'Invalid followup created an orphan reminder.');
    client_check(count($owner->list(['q'=>'Test PIC'])['clients'])===1, 'PIC search failed.');
    client_check(count($owner->list(['service_type'=>'VPS'])['clients'])===1, 'Service filtering failed.');
    $db->query("UPDATE tracs_reminders SET archived_at=NOW() WHERE id=$rid");
    client_check(!array_filter($getEvents(), fn($e)=>$e['source_id']===$id), 'Archived reminder leaked into Calendar.');
    $owner->renewService($client, ['service_id' => $detail['services'][0]['id'], 'new_renewal_date' => '2026-10-28'], 'Test Owner');
    $renewals = array_values(array_filter($owner->detail($client)['followups'], fn($f) => $f['action_type']==='renewal'));
    client_check(count($renewals)===1 && $renewals[0]['due_at']==='2026-10-28 09:00:00', 'Renewal rescheduling created duplicate reminders.');
    $db->query("INSERT INTO tracs_client_followups (client_id,title,status,completed_at) VALUES ($client,'Legacy unscheduled','completed','2026-09-01 09:00:00')");
    $legacyId = (int)$db->insert_id;
    $legacy = array_values(array_filter($owner->detail($client)['followups'], fn($f)=>(int)$f['id']===$legacyId))[0];
    client_check($legacy['completed_at']==='2026-09-01 09:00:00', 'Legacy completion history changed.');
    $owner->updateFollowup($legacyId, ['due_at'=>'2026-09-23T09:00','status'=>'open'], 'Test Owner');
    $legacy = array_values(array_filter($owner->detail($client)['followups'], fn($f)=>(int)$f['id']===$legacyId))[0];
    client_check((int)$legacy['reminder_id']>0 && $legacy['status']==='open', 'Legacy reminder was not linked when scheduled.');
    echo "PASS: grouped creation, invoice/tax/quotation/renewal events, canonical edits in both directions, completion/reopening, ownership, deduplication, rollback, validation, filters and migration rerun.\n";
} finally {
    $db->query("DROP DATABASE `$name`");
    $db->close();
}
