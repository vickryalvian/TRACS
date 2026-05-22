<?php
$initials = tracs_user_initials($user['display_name'] ?? '', $user['email'] ?? 'U');
$avatarUrl = tracs_user_avatar_url($user);
$roleClass = tracs_user_role_badge_class($user['role_slug'] ?? 'agent');
$statusClass = tracs_user_status_badge_class($user['status'] ?? 'active');
$payload = um_user_payload($user);
$isIntern = !empty($user['is_intern']);
$showInternMeta = $um_show_intern_meta ?? true;
$monitor = (string)($user['internship_monitor_state'] ?? '');
$internBadgeClass = match ($monitor) {
    'end_passed', 'terminated' => 'b-critical',
    'ending_soon' => 'b-expiring',
    'completed' => 'b-done',
    'extended' => 'b-info',
    'upcoming' => 'b-info',
    default => 'b-active',
};
$remaining = $user['internship_days_remaining'] ?? null;
$remainingLabel = is_numeric($remaining)
    ? (((int)$remaining < 0) ? 'End date passed' : ((int)$remaining . ' days left'))
    : '';
$twoFactorReady = !empty($user['two_factor_enabled']) && empty($user['two_factor_reset_required']) && !empty($user['two_factor_confirmed_at']);
?>
<article class="um-user-card <?=($isIntern && $showInternMeta) ? 'um-user-card-intern ' . esc($monitor ? 'intern-' . $monitor : 'intern-active') : ''?>">
  <div class="um-user-card-main">
    <button type="button" class="um-avatar tracs-avatar" onclick="umOpenUserDrawer(this)" data-user="<?=$payload?>" data-avatar-user-id="<?=esc((string)$user['id'])?>" data-avatar-initials="<?=esc($initials)?>" style="<?=!empty($user['avatar_initials_color'])?'--um-avatar-bg:'.esc($user['avatar_initials_color']):''?>"><?php if($avatarUrl): ?><img src="<?=esc($avatarUrl)?>" alt="" loading="lazy" decoding="async"><?php else: ?><span><?=$initials?></span><?php endif; ?></button>
    <div class="um-user-main">
      <button type="button" class="um-user-name" onclick="umOpenUserDrawer(this)" data-user="<?=$payload?>"><?=esc($user['display_name'])?></button>
      <div class="um-user-meta">@<?=esc($user['username'])?> · <?=esc($user['email'])?></div>
      <div class="um-user-meta"><?=esc($user['position'] ?: 'No position')?> · Last active <?=um_dt($user['last_activity_at'] ?? null)?></div>
    </div>
  </div>
  <div class="um-user-card-badges" aria-label="User status badges">
    <?=um_badge($user['role_name'] ?? 'Agent', $roleClass)?>
    <?php if($isIntern && $showInternMeta): ?>
      <?=um_badge(ucwords(str_replace('_', ' ', (string)($user['internship_status'] ?: 'active'))), $internBadgeClass)?>
    <?php endif; ?>
    <?=um_badge(ucfirst($user['status'] ?? 'active'), $statusClass)?>
    <?=um_badge($twoFactorReady ? '2FA enabled' : '2FA setup required', $twoFactorReady ? 'b-active' : 'b-warning')?>
    <span class="badge b-info" title="<?=count($user['role_permissions'] ?? [])?> role permissions"><span class="badge-dot"></span><?=count($user['role_permissions'] ?? [])?> permissions</span>
  </div>
  <?php if($isIntern && $showInternMeta): ?>
    <div class="um-intern-card-meta">
      <span>Intern</span>
      <span><?=esc($user['university_name'] ?: 'University not set')?></span>
      <span><?=esc($user['internship_end_date'] ? 'Ends ' . $user['internship_end_date'] : 'End date not set')?></span>
      <?php if($remainingLabel): ?><span><?=esc($remainingLabel)?></span><?php endif; ?>
      <span>Mentor: <?=esc($user['mentor_name'] ?: 'Unassigned')?></span>
    </div>
  <?php endif; ?>
  <div class="um-user-card-actions">
    <button type="button" class="btn btn-ghost btn-icon" onclick="umOpenUserDrawer(this)" data-user="<?=$payload?>" title="View profile" aria-label="View profile"><i data-lucide="eye" class="icon-sm"></i></button>
    <?php if($can_update_user): ?>
      <button type="button" class="btn btn-ghost btn-icon" onclick="umEditUser(this)" data-user="<?=$payload?>" title="Edit user" aria-label="Edit user"><i data-lucide="pencil" class="icon-sm"></i></button>
    <?php endif; ?>
    <button type="button" class="btn btn-ghost btn-icon" onclick="umOpenPermissionDrawer(this)" data-user="<?=$payload?>" title="Permissions" aria-label="Permissions"><i data-lucide="shield-check" class="icon-sm"></i></button>
    <details class="row-action-menu">
      <summary class="btn btn-ghost btn-icon" title="More actions" aria-label="More actions for <?=esc($user['display_name'])?>"><i data-lucide="more-vertical" class="icon-sm"></i></summary>
      <div class="row-action-popover">
        <?php if(($user['status'] ?? '') === 'active' && $can_suspend_user): ?>
          <form method="post" onsubmit="return umSubmitReason(this, 'Suspension reason is required.')">
            <?=csrf_input()?><input type="hidden" name="action" value="set_user_status"><input type="hidden" name="user_id" value="<?=$user['id']?>"><input type="hidden" name="status" value="suspended"><input type="hidden" name="reason">
            <button type="submit" class="btn btn-danger btn-sm"><i data-lucide="ban" class="icon-sm"></i>Suspend</button>
          </form>
        <?php elseif(($user['status'] ?? '') !== 'active' && $can_activate_user): ?>
          <form method="post" onsubmit="return confirm('Activate this user?')">
            <?=csrf_input()?><input type="hidden" name="action" value="set_user_status"><input type="hidden" name="user_id" value="<?=$user['id']?>"><input type="hidden" name="status" value="active"><input type="hidden" name="reason" value="Activated by admin">
            <button type="submit" class="btn btn-success btn-sm"><i data-lucide="circle-check" class="icon-sm"></i>Activate</button>
          </form>
        <?php endif; ?>
        <?php if($can_reset_password): ?>
          <form method="post" onsubmit="return umConfirmReset(this)">
            <?=csrf_input()?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="<?=$user['id']?>"><input type="hidden" name="reason">
            <button type="submit" class="btn btn-ghost btn-sm"><i data-lucide="key-round" class="icon-sm"></i>Reset Password</button>
          </form>
        <?php endif; ?>
        <?php if($can_reset_2fa): ?>
          <button type="button" class="btn btn-ghost btn-sm" onclick="umOpenTwoFactorResetModal(this)" data-user="<?=$payload?>"><i data-lucide="shield-off" class="icon-sm"></i>Reset 2FA</button>
        <?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="?tab=activity&target_user_id=<?=$user['id']?>"><i data-lucide="history" class="icon-sm"></i>View Activity</a>
      </div>
    </details>
  </div>
</article>
