<?php
@include 'config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id){
   header('location:login.php');
   exit;
}

$message = [];

/* ================= Helpers ================= */
function getLivePromoPrice(PDO $conn, int $pid, float $basePrice): float {
   // Guard
   if (!is_finite($basePrice) || $basePrice < 0) $basePrice = 0.0;

   $now = date('Y-m-d H:i:s');
   $q = $conn->prepare("
      SELECT promo_price, discount_percent
      FROM promotions
      WHERE product_id = ? AND active = 1
        AND (starts_at IS NULL OR starts_at <= ?)
        AND (ends_at   IS NULL OR ends_at   >= ?)
      ORDER BY id DESC
      LIMIT 1
   ");
   $q->execute([$pid, $now, $now]);
   if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
      $hasPP   = isset($row['promo_price']) && $row['promo_price'] !== '' && is_numeric($row['promo_price']);
      $hasDisc = isset($row['discount_percent']) && $row['discount_percent'] !== '' && is_numeric($row['discount_percent']);

      if ($hasPP) {
         $pp = (float)$row['promo_price'];
         return ($pp >= 0 && $pp < $basePrice) ? $pp : $basePrice;
      }
      if ($hasDisc) {
         $d = (float)$row['discount_percent'];
         if ($d > 0 && $d <= 95) {
            $calc = max(0.0, $basePrice * (1 - $d/100));
            return ($calc < $basePrice - 0.0001) ? $calc : $basePrice;
         }
      }
   }
   return $basePrice;
}

/* ============= Wishlist (by pid) ============= */
if(isset($_POST['add_to_wishlist'])){
   $pid = isset($_POST['pid']) ? (int)$_POST['pid'] : 0;

   $pstmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE id = ? LIMIT 1");
   $pstmt->execute([$pid]);
   $prod = $pstmt->fetch(PDO::FETCH_ASSOC);

   if(!$prod){
      $message[] = 'Product not found.';
   }else{
      $checkW = $conn->prepare("SELECT 1 FROM wishlist WHERE pid = ? AND user_id = ? LIMIT 1");
      $checkW->execute([$pid, $user_id]);

      $checkC = $conn->prepare("SELECT 1 FROM cart WHERE pid = ? AND user_id = ? LIMIT 1");
      $checkC->execute([$pid, $user_id]);

      if($checkW->rowCount() > 0){
         $message[] = 'Already in wishlist!';
      }elseif($checkC->rowCount() > 0){
         $message[] = 'Already in cart!';
      }else{
         $ins = $conn->prepare("INSERT INTO wishlist(user_id, pid, name, price, image) VALUES(?,?,?,?,?)");
         $ins->execute([$user_id, $prod['id'], $prod['name'], $prod['price'], $prod['image']]);
         $message[] = 'Added to wishlist!';
      }
   }
}

/* ========= Add to CART with stock subtraction + promo price ========= */
if(isset($_POST['add_to_cart'])){
   $pid  = isset($_POST['pid']) && is_numeric($_POST['pid']) ? (int)$_POST['pid'] : 0;
   $reqQ = isset($_POST['p_qty']) && is_numeric($_POST['p_qty']) ? (int)$_POST['p_qty'] : 1;
   $reqQ = max(1, $reqQ);

   try{
      $conn->beginTransaction();

      // Lock product row
      $pstmt = $conn->prepare("SELECT id, name, price, image, quantity FROM products WHERE id = ? FOR UPDATE");
      $pstmt->execute([$pid]);
      $prod = $pstmt->fetch(PDO::FETCH_ASSOC);

      if(!$prod){
         $conn->rollBack();
         $message[] = 'Product not found.';
      }else{
         $avail = (int)($prod['quantity'] ?? 0);
         if ($avail <= 0){
            $conn->rollBack();
            $message[] = 'Out of stock.';
         }else{
            $addQty   = min($reqQ, $avail);
            $newStock = $avail - $addQty;

            $basePrice = (float)$prod['price'];
            $unitPrice = getLivePromoPrice($conn, $pid, $basePrice);

            // Lock/Upsert cart row
            $csel = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND pid = ? FOR UPDATE");
            $csel->execute([$user_id, $pid]);

            if($row = $csel->fetch(PDO::FETCH_ASSOC)){
               $newCartQty = (int)$row['quantity'] + $addQty;
               $cupd = $conn->prepare("UPDATE cart SET quantity = ?, price = ?, name = ?, image = ? WHERE id = ?");
               $cupd->execute([$newCartQty, $unitPrice, $prod['name'], $prod['image'], $row['id']]);
            }else{
               $cins = $conn->prepare("INSERT INTO cart (user_id, pid, name, price, quantity, image) VALUES (?,?,?,?,?,?)");
               $cins->execute([$user_id, $prod['id'], $prod['name'], $unitPrice, $addQty, $prod['image']]);
            }

            // Subtract stock
            $up = $conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
            $up->execute([$newStock, $pid]);

            $conn->commit();

            if($addQty < $reqQ){
               $message[] = "Only {$addQty} left; added {$addQty} to cart.";
            }else{
               $message[] = 'Added to cart!';
            }
         }
      }
   }catch(Exception $e){
      if($conn->inTransaction()){ $conn->rollBack(); }
      $message[] = 'Could not add to cart. Please try again.';
   }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Quick View</title>

   <!-- Tailwind CDN -->
   <script src="https://cdn.tailwindcss.com"></script>
   <script>
      tailwind.config = {
         theme: {
            extend: {
               colors: {
                  primary: '#8B4513',
                  secondary: '#A0522D',
                  accent: '#D2B48C',
                  dark: '#3E2723',
                  darker: '#1B0F0A'
               },
               fontFamily: {
                  gaming: ['Orbitron', 'monospace'],
                  inter: ['Inter', 'sans-serif']
               }
            }
         }
      }
   </script>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
   <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

   <style>
      *{margin:0;padding:0;box-sizing:border-box}
      body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#1B0F0A 0%,#3E2723 50%,#5D4037 100%);color:#fff;overflow-x:hidden}
      .hero-bg{background:radial-gradient(circle at 20% 80%, rgba(139,69,19,.35) 0%, transparent 55%),radial-gradient(circle at 80% 20%, rgba(210,180,140,.35) 0%, transparent 55%),radial-gradient(circle at 40% 40%, rgba(160,82,45,.35) 0%, transparent 55%)}
      .neon-glow{box-shadow:0 0 20px rgba(139,69,19,.5),0 0 40px rgba(160,82,45,.3),0 0 60px rgba(210,180,140,.2)}
      .glass-effect{background:rgba(255,255,255,.08);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.18)}
      .hover-glow:hover{transform:translateY(-5px);box-shadow:0 10px 25px rgba(139,69,19,.35);transition:.3s ease}
      .gradient-text{background:linear-gradient(45deg,#8B4513,#A0522D,#D2B48C);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}

      .product-card{background:linear-gradient(180deg,rgba(62,39,35,.92),rgba(62,39,35,.84));border:1px solid rgba(210,180,140,.28);border-radius:22px;overflow:hidden;transition:transform .35s ease,box-shadow .35s ease,border-color .35s ease}
      .product-card:hover{transform:translateY(-8px);border-color:rgba(210,180,140,.55);box-shadow:0 22px 48px rgba(160,82,45,.35)}
      .price-badge{font-size:1.05rem;letter-spacing:.3px;padding:.55rem .95rem;border:1px solid rgba(255,255,255,.18)}
      .product-title{font-weight:800;letter-spacing:.2px;color:#FFF7EE;text-shadow:0 1px 0 rgba(0,0,0,.55);line-height:1.25}
      .qty{background:rgba(255,255,255,.08)}
      .qty:focus{outline:none;box-shadow:0 0 0 3px rgba(210,180,140,.35)}
      .pill{background:#fef3c7;color:#92400e;border:1px solid #f59e0b}
      .badge-oos{background:#fee2e2 !important;color:#991b1b !important;border-color:#ef4444 !important}
      .old-price{color:#e2e8f0;opacity:.98;text-decoration:line-through;font-weight:600;text-shadow:0 1px 1px rgba(0,0,0,.35)}
   </style>
</head>
<body>
<?php include 'header.php'; ?>

<!-- Hero / Header -->
<section class="relative min-h-[35vh] md:min-h-[45vh] flex items-center justify-center overflow-hidden hero-bg">
  <div class="container mx-auto px-6 lg:px-12 relative z-10 text-center">
     <h1 class="text-5xl lg:text-7xl font-bold leading-tight mb-4">
       <span class="gradient-text font-gaming">QUICK</span> <span class="text-white">VIEW</span>
     </h1>
     <div class="h-1 w-28 bg-gradient-to-r from-[#8B4513] to-[#D2B48C] rounded-full mx-auto mb-6"></div>
     <p class="text-lg md:text-xl text-gray-200 max-w-3xl mx-auto">See the details you need and add to cart instantly.</p>
  </div>
</section>

<section id="quick-view" class="py-16">
  <div class="container mx-auto px-6 lg:px-12">
    <?php
      $pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
      $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
      $stmt->execute([$pid]);
      if($stmt->rowCount() > 0){
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        $qty     = (int)($p['quantity'] ?? 0);
        $inStock = $qty > 0;

        $basePrice = (float)$p['price'];
        $livePrice = getLivePromoPrice($conn, $pid, $basePrice);
        $isDiscount= $livePrice < $basePrice - 0.0001;
    ?>
    <form action="" method="POST" class="group max-w-4xl mx-auto">
      <!-- Hidden first so any submit posts the pid -->
      <input type="hidden" name="pid" value="<?= (int)$p['id']; ?>">

      <div class="product-card relative neon-glow">
        <!-- top bar -->
        <div class="px-4 py-3 flex items-center justify-between">
          <div class="inline-flex items-center gap-2 price-badge rounded-full bg-gradient-to-r from-[#8B4513] to-[#D2B48C] text-white">
            <i class="fas fa-tag"></i>
            <?php if($isDiscount): ?>
              <span class="font-semibold">Rs <?= number_format($livePrice,2); ?></span>
              <span class="old-price ml-2 text-sm">Was Rs <?= number_format($basePrice,2); ?></span>
            <?php else: ?>
              <span class="font-semibold">Rs <?= number_format($basePrice,2); ?></span>
            <?php endif; ?>
          </div>

          <div class="flex items-center gap-2">
            <?php if(!$inStock): ?>
              <span class="pill badge-oos text-xs px-2 py-1 rounded border">Out of stock</span>
            <?php elseif($qty < 10): ?>
              <span class="pill text-xs px-2 py-1 rounded border">Only <?= $qty; ?> left</span>
            <?php endif; ?>
            <a href="shop.php" class="w-10 h-10 glass-effect rounded-full flex items-center justify-center text-white hover-glow" aria-label="Back">
              <i class="fas fa-times"></i>
            </a>
          </div>
        </div>

        <!-- Image -->
        <div class="aspect-square overflow-hidden">
          <img src="uploaded_img/<?=
              htmlspecialchars($p['image']); ?>"
              alt="<?= htmlspecialchars($p['name']); ?>"
              class="w-full h-full object-cover"
              onerror="this.src='uploaded_img/placeholder.png';">
        </div>

        <!-- Info -->
        <div class="p-6 md:p-8">
          <h3 class="product-title text-2xl mb-3"><?= htmlspecialchars($p['name']); ?></h3>

          <?php if(!empty($p['details'])): ?>
          <p class="text-gray-200 mb-6 leading-relaxed"><?= nl2br(htmlspecialchars($p['details'])); ?></p>
          <?php endif; ?>

          <!-- Quantity -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-200 mb-2">Quantity</label>
            <input
               type="number"
               name="p_qty"
               min="1"
               <?= $inStock ? 'value="1"' : 'value="0"'; ?>
               <?= $inStock ? 'max="'.$qty.'"' : 'disabled'; ?>
               class="qty w-32 px-4 py-3 glass-effect rounded-xl text-white text-center">
          </div>

          <!-- Actions -->
          <div class="grid sm:grid-cols-2 gap-3">
            <button type="submit" name="add_to_cart"
                    class="w-full bg-gradient-to-r from-[#8B4513] to-[#D2B48C] text-white py-4 rounded-xl font-semibold hover-glow transition"
                    <?= $inStock ? '' : 'disabled style="opacity:.6;cursor:not-allowed"'; ?>>
              <i class="fas fa-shopping-cart mr-2"></i> <?= $inStock ? 'Add to Cart' : 'Unavailable' ?>
            </button>

            <button type="submit" name="add_to_wishlist"
                    class="w-full glass-effect text-white py-4 rounded-xl font-semibold hover-glow">
              <i class="fas fa-heart mr-2"></i> Add to Wishlist
            </button>
          </div>
        </div>
      </div>
    </form>
    <?php
      } else {
        echo '<div class="text-center">
                <div class="glass-effect p-12 rounded-3xl max-w-md mx-auto">
                  <i class="fas fa-box-open text-6xl" style="color:#CD853F"></i>
                  <p class="text-2xl text-gray-200 font-medium mt-4">No products found!</p>
                  <a href="shop.php"
                     class="mt-6 inline-flex items-center justify-center bg-gradient-to-r from-[#8B4513] to-[#D2B48C] text-white px-6 py-3 rounded-xl font-semibold hover-glow transition">
                    <i class="fas fa-store mr-2"></i> Back to Shop
                  </a>
                </div>
              </div>';
      }
    ?>
  </div>
</section>

<?php include 'footer.php'; ?>
<script src="js/script.js"></script>
</body>
</html>
