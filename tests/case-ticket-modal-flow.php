<?php

function case_ticket_modal_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Case ticket modal flow check failed: {$message}\n");
        exit(1);
    }
}

$footer = file_get_contents(__DIR__ . '/../public/includes/footer.php');
$script = file_get_contents(__DIR__ . '/../public/assets/tracs.js');

case_ticket_modal_assert($footer !== false, 'Footer markup is unreadable.');
case_ticket_modal_assert($script !== false, 'TRACS script is unreadable.');

case_ticket_modal_assert(
    str_contains($footer, "editCaseFromTicket('caseNotes')"),
    'Add note action no longer focuses the case notes field.'
);
case_ticket_modal_assert(
    str_contains($footer, "editCaseFromTicket('caseNextCheck')"),
    'Set next check action no longer focuses the next-check field.'
);
case_ticket_modal_assert(
    str_contains($script, 'function caseTicketStatusButtons()')
        && str_contains($script, 'function setCaseTicketStatusPending(pending,activeButton=null)'),
    'Ticket status buttons no longer share pending/loading state.'
);
case_ticket_modal_assert(
    str_contains($script, "updateCaseStatusImmediately(currentCaseTicketId,status,'drawer_action')")
        && str_contains($script, "updateCaseStatusImmediately(id,'completed','drawer_action')"),
    'Ticket footer status actions no longer route through the shared status flow.'
);
case_ticket_modal_assert(
    str_contains($script, 'let caseTicketRequestSeq = 0;')
        && str_contains($script, 'requestSeq!==caseTicketRequestSeq'),
    'Ticket detail loading is missing stale-response protection.'
);
case_ticket_modal_assert(
    str_contains($script, '[editBtn,noteBtn,reminderBtn,deleteBtn].some'),
    'More-actions permission refresh no longer includes every menu item.'
);
case_ticket_modal_assert(
    str_contains($script, 'function closeTopModal()')
        && str_contains($script, "if(e.key==='Escape' && !tracsDialogActive)closeTopModal();")
        && str_contains($script, 'tracsCloseModalElement(e.target);'),
    'Topmost modal close behavior regressed.'
);

echo "Case ticket modal flow check passed.\n";
