<?php
@include 'config.php';
session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if(!isset($admin_id)){
   header('location:login.php');
   exit;
}

$message = [];

/* ===============================
   Flash message from redirects
================================= */
if(isset($_SESSION['flash_msg']) && $_SESSION['flash_msg'] !== ''){
   $message[] = $_SESSION['flash_msg'];
   unset($_SESSION['flash_msg']);
}

/* ===============================
   Delete message
================================= */
if(isset($_GET['delete'])){
   $delete_id = (int)$_GET['delete'];
   $delete_message = $conn->prepare("DELETE FROM `message` WHERE id = ?");
   $delete_message->execute([$delete_id]);
   $_SESSION['flash_msg'] = 'Message deleted.';
   header('location:admin_contacts.php');
   exit;
}

/* ===============================
   Stats
================================= */
$total_messages = (int)$conn->query("SELECT COUNT(*) FROM `message`")->fetchColumn();
$linked_messages = (int)$conn->query("SELECT COUNT(*) FROM `message` WHERE user_id IS NOT NULL AND user_id <> '' AND user_id <> 0")->fetchColumn();
$unique_senders = (int)$conn->query("SELECT COUNT(DISTINCT email) FROM `message` WHERE email IS NOT NULL AND email <> ''")->fetchColumn();
$with_phone     = (int)$conn->query("SELECT COUNT(*) FROM `message` WHERE `number` IS NOT NULL AND `number` <> ''")->fetchColumn();

/* ===============================
   Search & filter
================================= */
function build_contacts_where(string $q, string $link, array &$params): string {
   $clauses = [];
   if(trim($q) !== ''){
      $like = "%{$q}%";
      $clauses[] = "(name LIKE ? OR email LIKE ? OR `number` LIKE ? OR message LIKE ? OR CAST(id AS CHAR) LIKE ?)";
      array_push($params, $like, $like, $like, $like, $like);
   }
   if($link === 'linked'){
      $clauses[] = "(user_id IS NOT NULL AND user_id <> '' AND user_id <> 0)";
   } elseif ($link === 'guest'){
      $clauses[] = "(user_id IS NULL OR user_id = '' OR user_id = 0)";
   }
   return $clauses ? (' WHERE '.implode(' AND ', $clauses).' ') : '';
}

$q    = isset($_GET['q']) ? trim($_GET['q']) : '';
$link = isset($_GET['link']) ? trim($_GET['link']) : '';

$params = [];
$where  = build_contacts_where($q, $link, $params);

// count results for label
$stmt_count = $conn->prepare("SELECT COUNT(*) FROM `message`".$where);
$stmt_count->execute($params);
$total_results = (int)$stmt_count->fetchColumn();

/* ===============================
   Fetch messages (latest first)
================================= */
$sql = "SELECT * FROM `message`".$where." ORDER BY id DESC";
$select_message = $conn->prepare($sql);
$select_message->execute($params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Admin • Messages</title>

   <!-- Tailwind CSS -->
   <script src="https://cdn.tailwindcss.com"></script>
   
   <!-- Font Awesome -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <style>
     .line-clamp-1{display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden}
     .line-clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
     .line-clamp-4{display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}
   </style>
</head>
<body class="bg-gray-50">
<?php include 'admin_header.php'; ?>

<!-- Main Content -->
<div class="ml-64 pt-16 min-h-screen">
   <div class="p-6">
      
      <!-- Header -->
      <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 text-white mb-6">
         <div class="flex items-center justify-between">
            <div>
               <h1 class="text-2xl font-bold">Messages</h1>
               <p class="text-blue-100"><?= date('l, F j, Y'); ?></p>
            </div>
            <a href="admin_page.php" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded transition">
               <i class="fas fa-home"></i> Dashboard
            </a>
         </div>
      </div>

      <!-- Flash Messages -->
      <?php if(!empty($message)): ?>
        <div class="mb-4 space-y-2">
          <?php foreach($message as $msg): ?>
            <div class="flex items-center gap-2 bg-blue-50 text-blue-800 border border-blue-200 rounded px-4 py-2">
              <i class="fas fa-info-circle"></i>
              <span><?= htmlspecialchars($msg); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
         <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
               <div class="bg-orange-100 p-2 rounded">
                  <i class="fas fa-envelope text-orange-600"></i>
               </div>
               <div class="ml-3">
                  <p class="text-sm text-gray-600">Total Messages</p>
                  <h3 class="text-xl font-bold"><?= $total_messages; ?></h3>
               </div>
            </div>
            <a href="admin_contacts.php" class="text-blue-600 text-sm mt-2 inline-block">View All →</a>
         </div>

         <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
               <div class="bg-green-100 p-2 rounded">
                  <i class="fas fa-link text-green-600"></i>
               </div>
               <div class="ml-3">
                  <p class="text-sm text-gray-600">Linked to Accounts</p>
                  <h3 class="text-xl font-bold"><?= $linked_messages; ?></h3>
               </div>
            </div>
            <a href="admin_contacts.php?link=linked" class="text-blue-600 text-sm mt-2 inline-block">Filter Linked →</a>
         </div>

         <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
               <div class="bg-indigo-100 p-2 rounded">
                  <i class="fas fa-user-friends text-indigo-600"></i>
               </div>
               <div class="ml-3">
                  <p class="text-sm text-gray-600">Unique Senders</p>
                  <h3 class="text-xl font-bold"><?= $unique_senders; ?></h3>
               </div>
            </div>
            <span class="text-gray-400 text-sm mt-2 inline-block">By email</span>
         </div>

         <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
               <div class="bg-teal-100 p-2 rounded">
                  <i class="fas fa-phone text-teal-600"></i>
               </div>
               <div class="ml-3">
                  <p class="text-sm text-gray-600">With Phone #</p>
                  <h3 class="text-xl font-bold"><?= $with_phone; ?></h3>
               </div>
            </div>
            <span class="text-gray-400 text-sm mt-2 inline-block">Have a contact number</span>
         </div>
      </div>

      <!-- Messages Manager -->
      <div class="bg-white rounded-lg shadow mb-6">
         <!-- Toolbar -->
         <div class="p-4 md:p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
               <div>
                  <h3 class="text-lg font-semibold">Inbox</h3>
                  <?php if($q !== '' || ($link === 'linked' || $link === 'guest')): ?>
                    <p class="text-gray-500 text-sm">
                      Showing <span class="font-medium"><?= $total_results; ?></span> result(s)
                      <?php if($q !== ''): ?> for “<span class="font-medium"><?= htmlspecialchars($q); ?></span>”<?php endif; ?>
                      <?php if($link !== ''): ?> (<?= htmlspecialchars($link); ?>)<?php endif; ?>
                      <a href="admin_contacts.php" class="text-blue-600 hover:underline ml-2">Reset</a>
                    </p>
                  <?php else: ?>
                    <p class="text-gray-500 text-sm">Search, filter and manage messages</p>
                  <?php endif; ?>
               </div>

               <!-- Search / Filter Form -->
               <form method="GET" class="w-full md:w-auto">
                  <div class="flex flex-col sm:flex-row gap-2">
                     <div class="relative">
                        <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input
                          type="text"
                          name="q"
                          value="<?= htmlspecialchars($q); ?>"
                          placeholder="Search name, email, phone, text, ID…"
                          class="w-full sm:w-80 pl-10 pr-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                     </div>
                     <select name="link" class="w-full sm:w-44 py-2 px-3 border rounded bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All</option>
                        <option value="linked" <?= $link==='linked'?'selected':''; ?>>Linked to account</option>
                        <option value="guest"  <?= $link==='guest'?'selected':'';  ?>>Guest</option>
                     </select>
                     <button class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition">
                        <i class="fas fa-filter"></i> Apply
                     </button>
                  </div>
               </form>
            </div>
         </div>

         <!-- Table -->
         <div class="p-4 md:p-6 overflow-x-auto">
            <table class="w-full text-sm">
               <thead>
                  <tr class="text-left border-b">
                     <th class="py-2">Sender</th>
                     <th class="py-2">Email</th>
                     <th class="py-2">Phone</th>
                     <th class="py-2">Type</th>
                     <th class="py-2">Message</th>
                     <th class="py-2 text-right">Actions</th>
                  </tr>
               </thead>
               <tbody>
               <?php
                 if($select_message->rowCount() > 0){
                   while($m = $select_message->fetch(PDO::FETCH_ASSOC)){
                     $mid   = (int)$m['id'];
                     $uid   = $m['user_id'];
                     $isLinked = ($uid !== null && $uid !== '' && (int)$uid !== 0);
                     $badgeClass = $isLinked ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700';
               ?>
                  <tr class="border-b hover:bg-gray-50 align-top">
                     <td class="py-2">
                        <div class="flex items-start gap-3">
                           <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                              <i class="fas fa-user text-gray-400"></i>
                           </div>
                           <div>
                              <div class="font-medium line-clamp-1"><?= htmlspecialchars($m['name']); ?></div>
                              <?php if($isLinked): ?>
                                <div class="text-xs text-gray-500">User ID #<?= htmlspecialchars($uid); ?></div>
                              <?php else: ?>
                                <div class="text-xs text-gray-500">Guest</div>
                              <?php endif; ?>
                           </div>
                        </div>
                     </td>
                     <td class="py-2">
                        <?php if(!empty($m['email'])): ?>
                          <a href="mailto:<?= htmlspecialchars($m['email']); ?>?subject=Re:%20Your%20message"
                             class="text-blue-600 hover:underline">
                            <?= htmlspecialchars($m['email']); ?>
                          </a>
                        <?php else: ?>
                          <span class="text-gray-400">—</span>
                        <?php endif; ?>
                     </td>
                     <td class="py-2">
                        <?php if(!empty($m['number'])): ?>
                          <a href="tel:<?= htmlspecialchars($m['number']); ?>" class="text-gray-700 hover:text-gray-900">
                            <?= htmlspecialchars($m['number']); ?>
                          </a>
                        <?php else: ?>
                          <span class="text-gray-400">—</span>
                        <?php endif; ?>
                     </td>
                     <td class="py-2">
                        <span class="text-xs px-2 py-1 rounded <?= $badgeClass; ?>">
                          <?= $isLinked ? 'Linked' : 'Guest'; ?>
                        </span>
                     </td>
                     <td class="py-2">
                        <div class="text-gray-700 line-clamp-4" title="<?= htmlspecialchars($m['message']); ?>">
                          <?= nl2br(htmlspecialchars($m['message'])); ?>
                        </div>
                     </td>
                     <td class="py-2">
                        <div class="flex items-center justify-end gap-2">
                           <?php if(!empty($m['email'])): ?>
                             <a href="mailto:<?= htmlspecialchars($m['email']); ?>?subject=Re:%20Your%20message"
                                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded transition">
                                <i class="fas fa-reply"></i> Reply
                             </a>
                           <?php endif; ?>
                           <a href="admin_contacts.php?delete=<?= $mid; ?>"
                              onclick="return confirm('Delete this message?');"
                              class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded transition">
                              <i class="fas fa-trash"></i> Delete
                           </a>
                        </div>
                     </td>
                  </tr>
               <?php
                   }
                 } else {
                   echo '<tr><td colspan="6" class="text-center text-gray-500 py-6">You have no messages.</td></tr>';
                 }
               ?>
               </tbody>
            </table>
         </div>
      </div>

      <!-- Quick Actions -->
      <div class="bg-white rounded-lg shadow p-6">
         <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
         <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="admin_products.php" class="bg-blue-600 hover:bg-blue-700 text-white p-3 rounded text-center transition-colors">
               <i class="fas fa-box mb-2 block"></i>
               <span class="text-sm">Manage Products</span>
            </a>
            <a href="admin_orders.php" class="bg-green-600 hover:bg-green-700 text-white p-3 rounded text-center transition-colors">
               <i class="fas fa-list mb-2 block"></i>
               <span class="text-sm">View Orders</span>
            </a>
            <a href="admin_users.php" class="bg-purple-600 hover:bg-purple-700 text-white p-3 rounded text-center transition-colors">
               <i class="fas fa-users mb-2 block"></i>
               <span class="text-sm">Manage Users</span>
            </a>
            <a href="admin_contacts.php" class="bg-orange-600 hover:bg-orange-700 text-white p-3 rounded text-center transition-colors">
               <i class="fas fa-envelope mb-2 block"></i>
               <span class="text-sm">Refresh Messages</span>
            </a>
         </div>
      </div>

   </div>
</div>

<script src="js/script.js"></script>

<!-- Reply Modal -->
<div id="replyModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/50" data-close-reply></div>

  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-xl rounded-lg shadow-lg overflow-hidden">
      <div class="px-5 py-3 border-b flex items-center justify-between">
        <h3 class="text-lg font-semibold"><i class="fas fa-reply mr-2 text-indigo-600"></i>Reply to Message</h3>
        <button class="text-gray-500 hover:text-gray-700" title="Close" data-close-reply>
          <i class="fas fa-times"></i>
        </button>
      </div>

      <form method="POST" action="reply_message.php" class="p-5 space-y-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">To</label>
          <input id="reply_to" name="to" type="email" readonly
                 class="w-full border rounded px-3 py-2 bg-gray-50 text-gray-700">
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">Subject</label>
          <input id="reply_subject" name="subject" type="text" required
                 class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">Message</label>
          <textarea id="reply_body" name="body" rows="7" required
                    class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="Write your reply..."></textarea>
        </div>
        <input type="hidden" id="reply_message_id" name="message_id" value="">

        <div class="flex items-center justify-end gap-2 pt-2">
          <button type="button" class="px-4 py-2 rounded bg-gray-100 hover:bg-gray-200 text-gray-800" data-close-reply>
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 rounded bg-indigo-600 hover:bg-indigo-700 text-white">
            Send Email
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const modal = document.getElementById('replyModal');
  const toEl = document.getElementById('reply_to');
  const subjectEl = document.getElementById('reply_subject');
  const bodyEl = document.getElementById('reply_body');
  const msgIdEl = document.getElementById('reply_message_id');

  function openModal(){ modal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
  function closeModal(){ modal.classList.add('hidden'); document.body.style.overflow = ''; }

  modal.addEventListener('click', (e) => {
    if(e.target.matches('[data-close-reply]')) closeModal();
  });
  document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape') closeModal(); });

  // Intercept ONLY the "Reply" buttons (keep your anchor syntax)
  const allMailto = document.querySelectorAll('a[href^="mailto:"]');
  allMailto.forEach(link => {
    const isReplyButton = /\breply\b/i.test(link.textContent || '') || !!link.querySelector('.fa-reply');
    if(!isReplyButton) return;

    link.addEventListener('click', (e) => {
      e.preventDefault();

      const href = link.getAttribute('href') || '';
      const raw = href.replace(/^mailto:/i, '');
      const [addr, qs] = raw.split('?');
      const email = decodeURIComponent((addr || '').trim());
      const params = new URLSearchParams(qs || '');
      const subj = params.get('subject') ? decodeURIComponent(params.get('subject')) : 'Re:';

      toEl.value = email || '';
      subjectEl.value = subj || 'Re:';
      bodyEl.value = '';

      // Extract message id from the Delete link in the same row
      let msgId = '';
      const tr = link.closest('tr');
      if(tr){
        const del = tr.querySelector('a[href*="admin_contacts.php?delete="]');
        if(del){
          try{
            const u = new URL(del.getAttribute('href'), window.location.href);
            msgId = u.searchParams.get('delete') || '';
          }catch(_){}
        }
      }
      msgIdEl.value = msgId;

      openModal();
    }, false);
  });
})();
</script>

</body>
</html>
