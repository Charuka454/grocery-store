<?php
@include 'config.php';
session_start();
$admin_id = $_SESSION['admin_id'] ?? null;
if(!$admin_id){ header('location:login.php'); exit; }

$message = [];

/* Create / Update */
if(isset($_POST['save_category'])){
  $cid   = isset($_POST['cid']) ? (int)$_POST['cid'] : 0;
  $name  = trim($_POST['name'] ?? '');
  $parent_id = ($_POST['parent_id'] ?? '') !== '' ? (int)$_POST['parent_id'] : null;

  if($name === ''){
    $message[] = 'Name is required.';
  }else{
    $slug = strtolower(preg_replace('~[^a-z0-9]+~', '-', iconv('UTF-8','ASCII//TRANSLIT',$name)));
    // ensure unique slug on create or when name changes
    if($cid === 0){
      $chk = $conn->prepare("SELECT id FROM categories WHERE slug=?");
      $chk->execute([$slug]);
      if($chk->rowCount()){ $slug .= '-'.substr(md5(microtime(true)),0,6); }
      $ins = $conn->prepare("INSERT INTO categories(name, slug, parent_id) VALUES(?,?,?)");
      $ins->execute([$name, $slug, $parent_id]);
      $message[] = 'Category created.';
    }else{
      // keep slug stable unless you prefer to re-generate it; here we update name & parent only
      $upd = $conn->prepare("UPDATE categories SET name=?, parent_id=? WHERE id=?");
      $upd->execute([$name, $parent_id, $cid]);
      $message[] = 'Category updated.';
    }
  }
  header('Location: admin_categories.php'); exit;
}

/* Delete */
if(isset($_GET['delete'])){
  $cid = (int)$_GET['delete'];
  // Optional: prevent delete if products exist
  $cnt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id=?");
  $cnt->execute([$cid]);
  if((int)$cnt->fetchColumn() > 0){
    $message[] = 'Cannot delete: category has products.';
  }else{
    $del = $conn->prepare("DELETE FROM categories WHERE id=?");
    $del->execute([$cid]);
    $message[] = 'Category deleted.';
  }
  header('Location: admin_categories.php'); exit;
}

/* Data */
$cats = $conn->query("
  SELECT c.id, c.name, c.slug, c.parent_id, c.created_at,
         (SELECT name FROM categories p WHERE p.id=c.parent_id) AS parent_name,
         (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) AS product_count
  FROM categories c
  ORDER BY c.name
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin • Categories</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css"/>
</head>
<body class="bg-gray-50">
<?php include 'admin_header.php'; ?>

<div class="ml-64 pt-16 min-h-screen">
  <div class="p-6">

    <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 text-white mb-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Categories</h1>
          <p class="text-blue-100"><?= date('l, F j, Y'); ?></p>
        </div>
        <a href="admin_products.php" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded transition">
          <i class="fas fa-box"></i> Products
        </a>
      </div>
    </div>

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

    <!-- Create / Edit -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <h3 class="text-lg font-semibold mb-4">Add / Edit Category</h3>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input type="hidden" name="cid" id="cid" value="0"/>
        <input type="text" name="name" id="name" placeholder="Category name"
               class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required/>
        <select name="parent_id" id="parent_id"
                class="w-full border rounded px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">No parent</option>
          <?php foreach($cats as $c): ?>
            <option value="<?= (int)$c['id']; ?>"><?= htmlspecialchars($c['name']); ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" name="save_category"
                class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded transition">
          <i class="fas fa-save"></i> Save
        </button>
      </form>
    </div>

    <!-- List -->
    <div class="bg-white rounded-lg shadow">
      <div class="p-4 md:p-6 border-b">
        <h3 class="text-lg font-semibold">All Categories</h3>
        <p class="text-gray-500 text-sm">Click “Edit” to load into the form above.</p>
      </div>
      <div class="p-4 md:p-6 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left border-b">
              <th class="py-2">Name</th>
              <th class="py-2">Parent</th>
              <th class="py-2">Slug</th>
              <th class="py-2">Products</th>
              <th class="py-2">Created</th>
              <th class="py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(count($cats)): foreach($cats as $c): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="py-2 font-medium"><?= htmlspecialchars($c['name']); ?></td>
                <td class="py-2"><?= htmlspecialchars($c['parent_name'] ?? '—'); ?></td>
                <td class="py-2 text-gray-600"><?= htmlspecialchars($c['slug']); ?></td>
                <td class="py-2"><?= (int)$c['product_count']; ?></td>
                <td class="py-2 text-gray-600"><?= htmlspecialchars($c['created_at']); ?></td>
                <td class="py-2">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded transition"
                      onclick="editCat(<?= (int)$c['id']; ?>,'<?= htmlspecialchars(addslashes($c['name'])); ?>',<?= $c['parent_id'] ? (int)$c['parent_id'] : 'null'; ?>)">
                      <i class="fas fa-pen"></i> Edit
                    </button>
                    <a href="admin_categories.php?delete=<?= (int)$c['id']; ?>"
                       onclick="return confirm('Delete this category?');"
                       class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded transition">
                      <i class="fas fa-trash"></i> Delete
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6" class="text-center text-gray-500 py-6">No categories yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
function editCat(id, name, parentId){
  document.getElementById('cid').value = id;
  document.getElementById('name').value = name;
  const sel = document.getElementById('parent_id');
  sel.value = parentId === null ? '' : String(parentId);
  sel.dispatchEvent(new Event('change'));
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<script src="js/script.js"></script>
</body>
</html>
