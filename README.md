# 🖼️ TinyPeexi — Laravel Plugin for Lossless Media Service

> **Upload, transform, and deliver images from your Laravel app in minutes.**

TinyPeexi is a Laravel package that gives you a clean, fluent PHP API to interact with a self-hosted [aipeexi / Lossless Media Service](https://github.com/tekkenking/aipeexi) instance. Upload images, generate optimized variants, apply effects, create e-commerce product shots, and get CDN-ready delivery URLs — all without leaving your Laravel codebase.

---

## 📦 Installation

### 1. Require via Composer

```bash
composer require tekkenking/tinypeexi
```

### 2. Publish the Config File

```bash
php artisan vendor:publish --provider="Tekkenking\TinyPeexi\TinyPeexiServiceProvider"
```

This creates `config/tinypeexi.php` in your Laravel project.

### 3. Add Your Credentials to `.env`

```env
TINYPEEXI_API_URL=http://localhost:8080
TINYPEEXI_TENANT_SLUG=dev
TINYPEEXI_API_KEY=sk_live_your_api_key_here
```

**That's it.** You're ready to use TinyPeexi.

---

## 🚀 Quick Start

```php
use Tekkenking\TinyPeexi\Facades\TinyPeexi;

// Upload an image
$asset = TinyPeexi::upload($request->file('photo'));

// Generate a 400px wide WebP variant
$variant = TinyPeexi::variant($asset->sha)
    ->resize(400)
    ->format('webp')
    ->quality(80)
    ->generate();

// Get the public URL
echo $variant->url();
// => http://localhost:8080/a/dev/abc123.../w400.webp
```

---

## 📖 Full API Reference

### Uploading Assets

#### Upload from a Form Request

```php
$asset = TinyPeexi::upload($request->file('image'));

echo $asset->sha; // "a1b2c3d4e5f6..."
```

#### Upload from a File Path

```php
$asset = TinyPeexi::upload('/path/to/local/image.png');

echo $asset->sha;
```

The `upload()` method returns an `AssetDto` with the asset's `sha` (SHA-256 hash), which you'll use as the identifier for all future operations on this image.

#### Uploading Multiple Files at Once

TinyPeexi supports uploading multiple files in a single HTTP request (the backend defaults to a max of 10 files per request).

```php
$files = $request->file('gallery_images'); // Array of UploadedFile

$assets = TinyPeexi::uploadMany($files);

foreach ($assets as $asset) {
    echo $asset->sha . "\n";
}
```

The `uploadMany()` method returns an array of `AssetDto` objects.

---

### Viewing & Displaying Images

When you want to display an uploaded image in your Blade templates or return it in a JSON API response, **you do not download the file contents back into Laravel**. 

Instead, TinyPeexi acts as a URL builder. You generate a public CDN/delivery URL that points directly to the Lossless Media backend, and the client's browser loads the image directly from there.

#### In a Blade Template:

```html
<!-- Display the original image -->
<img src="{{ TinyPeexi::variant($product->image_sha)->url() }}" alt="Product">

<!-- Display an optimized webp thumbnail -->
<img src="{{ TinyPeexi::variant($product->image_sha)->preset('thumbnail')->url() }}" alt="Thumbnail">
```

#### In an API Resource / Controller:

```php
public function show(Product $product)
{
    return response()->json([
        'id' => $product->id,
        'name' => $product->name,
        // Send the URL string to the frontend
        'image_url' => TinyPeexi::variant($product->image_sha)->ecommerceVariant()->url()
    ]);
}
```

By doing this, your Laravel application saves bandwidth and memory, as all image delivery and on-the-fly transformations are handled entirely by the `aipeexi` backend.

---

### Deleting Assets

```php
TinyPeexi::delete($sha);
```

Returns `true` on success, throws `TinyPeexiException` on failure.

---

### Generating Variants

The heart of TinyPeexi is the **fluent Variant Builder**. You start with `TinyPeexi::variant($sha)` and chain methods to describe the transformation you want.

#### Basic Resize

```php
$variant = TinyPeexi::variant($sha)
    ->resize(800)
    ->format('jpeg')
    ->quality(85)
    ->generate();

echo $variant->url();
```

#### Resize with Exact Dimensions

```php
$variant = TinyPeexi::variant($sha)
    ->resize(800, 600)    // width, height
    ->fit('cover')        // crop to fill
    ->generate();
```

#### Available Fit Modes

| Mode      | Behavior                                           |
|-----------|----------------------------------------------------|
| `cover`   | Crop to fill exact dimensions                      |
| `contain` | Fit within dimensions, letterbox if needed          |
| `fill`    | Stretch to exact dimensions (may distort)           |
| `inside`  | Fit inside, never upscale **(default)**             |
| `outside` | Fit outside, may upscale to cover                   |

#### Retina / HiDPI Support

```php
$variant = TinyPeexi::variant($sha)
    ->resize(400)
    ->dpr(2.0)    // Outputs 800px wide for retina displays
    ->generate();
```

---

### Cropping

#### Smart Crop

```php
$variant = TinyPeexi::variant($sha)
    ->resize(400, 400)
    ->fit('cover')
    ->crop('face')        // AI-powered face detection crop
    ->generate();
```

#### Available Crop Positions

| Position     | Description                          |
|--------------|--------------------------------------|
| `center`     | Center of image **(default)**        |
| `top`        | Top edge                             |
| `bottom`     | Bottom edge                          |
| `left`       | Left edge                            |
| `right`      | Right edge                           |
| `topleft`    | Top-left corner                      |
| `topright`   | Top-right corner                     |
| `bottomleft` | Bottom-left corner                   |
| `bottomright`| Bottom-right corner                  |
| `face`       | AI face detection (Pro tier)         |
| `entropy`    | Smart crop using entropy analysis    |
| `attention`  | Smart crop using saliency/attention  |

#### Manual Crop Rectangle

```php
$variant = TinyPeexi::variant($sha)
    ->cropRect(100, 50, 600, 400) // x, y, width, height
    ->generate();
```

#### Auto-Trim Whitespace

```php
$variant = TinyPeexi::variant($sha)
    ->trim(25) // threshold 0-255
    ->generate();
```

---

### Orientation

```php
// Rotate 90 degrees clockwise
$variant = TinyPeexi::variant($sha)
    ->rotate(90)
    ->generate();

// Flip horizontally (mirror)
$variant = TinyPeexi::variant($sha)
    ->flip('h')
    ->generate();

// Auto-fix EXIF orientation
$variant = TinyPeexi::variant($sha)
    ->autoOrient()
    ->generate();
```

| Rotate Values | Flip Values                          |
|---------------|--------------------------------------|
| `0`, `90`, `180`, `270`, `-90` | `h` (horizontal), `v` (vertical), `both` |

---

### Effects

```php
$variant = TinyPeexi::variant($sha)
    ->resize(800)
    ->blur(5.0)           // Gaussian blur (0.3 - 1000.0)
    ->sharpen(1.5)        // Sharpen (0.5 - 10.0)
    ->brightness(1.2)     // Brightness (0.1 - 10.0)
    ->contrast(1.1)       // Contrast (0.1 - 10.0)
    ->saturation(1.5)     // Saturation (0.0 - 2.0)
    ->generate();
```

#### Grayscale

```php
$variant = TinyPeexi::variant($sha)
    ->grayscale()
    ->format('jpeg')
    ->generate();
```

---

### E-Commerce Product Images

This is where TinyPeexi really shines. Creating uniform, professional product images is a single method call.

#### Using the Fluent Builder

```php
$variant = TinyPeexi::variant($sha)
    ->format('jpeg')
    ->ecommerce()          // Flag as e-commerce operation
    ->canvas(1024)         // 1024×1024 square canvas
    ->resize(800)          // Product fits within 800px
    ->pad(40)              // 40px breathing room
    ->background('white')  // Clean white background
    ->quality(90)
    ->generate();

echo $variant->url();
// => http://localhost:8080/a/dev/abc123.../w800.jpg
```

#### Using the Shorthand Helper

If you use the same e-commerce settings repeatedly, use the shorthand:

```php
$variant = TinyPeexi::variant($sha)->ecommerceVariant();
// Uses defaults from config: canvas=1024, pad=40, bg=white, jpeg, q=85
```

#### Custom E-Commerce Settings

```php
$variant = TinyPeexi::variant($sha)->ecommerceVariant(
    canvasSize: 800,
    padding: 20,
    background: 'transparent',
    format: 'png',
    quality: 100,
);
```

#### With AI Background Removal

```php
$variant = TinyPeexi::variant($sha)
    ->removeBg()           // Remove background via AI
    ->canvas(1024)
    ->pad(40)
    ->background('white')
    ->format('jpeg')
    ->quality(90)
    ->generate();
```

---

### Watermarks

#### Image Watermark

```php
// First, upload your watermark image (do this once)
$watermark = TinyPeexi::upload('/path/to/watermark.png');

// Then apply it to any variant
$variant = TinyPeexi::variant($sha)
    ->resize(1200)
    ->watermark(
        assetSha: $watermark->sha,
        position: 'bottomright',
        opacity: 0.5,
        scale: 0.15,
    )
    ->generate();
```

#### Available Watermark Positions

| Position      | Description                         |
|---------------|-------------------------------------|
| `center`      | Center of image                     |
| `topleft`     | Top-left corner                     |
| `topright`    | Top-right corner                    |
| `bottomleft`  | Bottom-left corner                  |
| `bottomright` | Bottom-right corner **(default)**   |
| `tile`        | Tile across entire image            |

---

### Using Presets

Define reusable transformation presets in `config/tinypeexi.php`:

```php
// In config/tinypeexi.php
'defaults' => [
    'thumbnail' => [
        'width' => 200,
        'height' => 200,
        'fit' => 'cover',
        'crop' => 'center',
        'format' => 'webp',
        'quality' => 80,
    ],
]
```

Then use them in your code:

```php
$variant = TinyPeexi::variant($sha)
    ->preset('thumbnail')
    ->generate();
```

You can define as many presets as you need (e.g., `hero_banner`, `avatar`, `og_image`, etc.).

---

### Batch Variant Generation

Generate multiple sizes at once:

```php
TinyPeexi::variant($sha)->batch(
    sizes: [400, 800, 1200],
    format: 'webp',
);
// Queues: w400.webp, w800.webp, w1200.webp
```

---

### Output Formats

| Format | Extension | Notes                          |
|--------|-----------|--------------------------------|
| `jpeg` | `.jpg`    | Best for photos                |
| `png`  | `.png`    | Lossless, supports transparency|
| `webp` | `.webp`   | Modern, smaller files **(default)** |
| `avif` | `.avif`   | Newest, smallest files         |
| `gif`  | `.gif`    | Animated images                |
| `tiff` | `.tiff`   | Print-quality                  |
| `ico`  | `.ico`    | Favicons                       |
| `svg`  | `.svg`    | Vector graphics                |

---

## ⚙️ Configuration Reference

After publishing, edit `config/tinypeexi.php`:

```php
return [
    // === REQUIRED ===
    'api_url'      => env('TINYPEEXI_API_URL', 'http://localhost:8080'),
    'delivery_url' => env('TINYPEEXI_DELIVERY_URL', env('TINYPEEXI_API_URL', 'http://localhost:8080')),
    'tenant_slug'  => env('TINYPEEXI_TENANT_SLUG', 'dev'),
    'api_key'      => env('TINYPEEXI_API_KEY', ''),

    // === ADVANCED (optional) ===
    'advanced' => [
        'timeout'     => env('TINYPEEXI_TIMEOUT', 10),      // seconds
        'retries'     => env('TINYPEEXI_RETRIES', 3),
        'retry_delay' => env('TINYPEEXI_RETRY_DELAY', 500), // ms

        'upload' => [
            'max_filesize_bytes' => env('TINYPEEXI_MAX_UPLOAD_SIZE', 26214400), // 25MB
            'allowed_mimes'      => ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'],
            'strip_metadata'     => env('TINYPEEXI_STRIP_METADATA', true),
            'auto_optimize'      => env('TINYPEEXI_AUTO_OPTIMIZE', false),
        ],
    ],

    // === PRESETS (optional) ===
    'defaults' => [
        'ecommerce' => [
            'canvas' => 1024, 'pad' => 40, 'background' => 'white',
            'format' => 'jpeg', 'quality' => 85,
        ],
        'thumbnail' => [
            'width' => 200, 'height' => 200, 'fit' => 'cover',
            'crop' => 'center', 'format' => 'webp', 'quality' => 80,
        ],
    ],
];
```

### Environment Variables

| Variable                    | Default              | Description                  |
|-----------------------------|----------------------|------------------------------|
| `TINYPEEXI_API_URL`        | `http://localhost:8080` | Base URL of aipeexi instance |
| `TINYPEEXI_DELIVERY_URL`   | Same as API URL      | CDN/delivery URL             |
| `TINYPEEXI_TENANT_SLUG`    | `dev`                | Your tenant slug             |
| `TINYPEEXI_API_KEY`        | *(empty)*            | Your API key                 |
| `TINYPEEXI_TIMEOUT`        | `10`                 | HTTP timeout (seconds)       |
| `TINYPEEXI_RETRIES`        | `3`                  | Retry count for failed requests |
| `TINYPEEXI_RETRY_DELAY`    | `500`                | Delay between retries (ms)   |
| `TINYPEEXI_MAX_UPLOAD_SIZE`| `26214400`           | Max upload size (bytes)      |
| `TINYPEEXI_STRIP_METADATA` | `true`               | Strip EXIF on upload         |
| `TINYPEEXI_AUTO_OPTIMIZE`  | `false`              | Auto-optimize on upload      |

---

## 🧑‍💻 Real-World Examples

### Profile Avatar Upload

```php
// In a Controller
public function updateAvatar(Request $request)
{
    $request->validate(['avatar' => 'required|image|max:5120']);

    $asset = TinyPeexi::upload($request->file('avatar'));

    // Generate a small square avatar
    $variant = TinyPeexi::variant($asset->sha)
        ->resize(200, 200)
        ->fit('cover')
        ->crop('face')
        ->format('webp')
        ->quality(80)
        ->generate();

    auth()->user()->update(['avatar_url' => $variant->url()]);

    return back()->with('success', 'Avatar updated!');
}
```

### Product Image Upload (E-Commerce)

```php
public function storeProduct(Request $request)
{
    $request->validate(['photo' => 'required|image|max:25600']);

    $asset = TinyPeexi::upload($request->file('photo'));

    // Professional product shot with white background
    $main = TinyPeexi::variant($asset->sha)->ecommerceVariant();

    // Also create a thumbnail
    $thumb = TinyPeexi::variant($asset->sha)
        ->preset('thumbnail')
        ->generate();

    Product::create([
        'name'          => $request->name,
        'image_sha'     => $asset->sha,
        'image_url'     => $main->url(),
        'thumbnail_url' => $thumb->url(),
    ]);

    return redirect()->route('products.index');
}
```

### Responsive Image Set

```php
$asset = TinyPeexi::upload($request->file('hero'));

// Generate multiple sizes for responsive <picture> element
TinyPeexi::variant($asset->sha)->batch(
    sizes: [400, 800, 1200, 1600],
    format: 'webp',
);

// In your Blade template:
// <picture>
//   <source srcset="/a/dev/{sha}/w400.webp 400w,
//                   /a/dev/{sha}/w800.webp 800w,
//                   /a/dev/{sha}/w1200.webp 1200w,
//                   /a/dev/{sha}/w1600.webp 1600w"
//           type="image/webp">
// </picture>
```

### Blog Post with Watermarked Images

```php
$watermarkAsset = TinyPeexi::upload('/path/to/blog-watermark.png');

$asset = TinyPeexi::upload($request->file('blog_image'));

$variant = TinyPeexi::variant($asset->sha)
    ->resize(1200)
    ->format('jpeg')
    ->quality(85)
    ->watermark(
        assetSha: $watermarkAsset->sha,
        position: 'bottomright',
        opacity: 0.3,
        scale: 0.1,
    )
    ->generate();
```

---

## 🔧 Error Handling

All errors throw `TinyPeexiException`:

```php
use Tekkenking\TinyPeexi\Exceptions\TinyPeexiException;

try {
    $asset = TinyPeexi::upload($request->file('image'));
} catch (TinyPeexiException $e) {
    Log::error('TinyPeexi upload failed', [
        'message' => $e->getMessage(),
        'code'    => $e->getCode(),
    ]);

    return back()->withErrors(['image' => 'Image upload failed. Please try again.']);
}
```

---

## 🧪 Testing

When writing tests, you can mock the TinyPeexi facade:

```php
use Tekkenking\TinyPeexi\Facades\TinyPeexi;
use Tekkenking\TinyPeexi\DTOs\AssetDto;

TinyPeexi::shouldReceive('upload')
    ->once()
    ->andReturn(new AssetDto('fake_sha_256_hash'));
```

---

## 📋 Method Cheat Sheet

| Method                     | Description                              |
|----------------------------|------------------------------------------|
| `TinyPeexi::upload($file)` | Upload an image, returns `AssetDto`     |
| `TinyPeexi::uploadMany(array $files)` | Upload multiple images, returns `AssetDto[]` |
| `TinyPeexi::delete($sha)`  | Delete an asset                          |
| `TinyPeexi::variant($sha)` | Start building a variant                 |
| `->resize($w, $h?)`        | Set target dimensions                    |
| `->fit($mode)`             | Set fit mode                              |
| `->dpr($ratio)`            | Set device pixel ratio                    |
| `->format($fmt)`           | Set output format                         |
| `->quality($q)`            | Set compression quality                   |
| `->progressive()`          | Enable progressive JPEG                   |
| `->stripMetadata()`        | Strip EXIF metadata                       |
| `->crop($position)`         | Set crop anchor                           |
| `->cropRect($x,$y,$w,$h)` | Manual crop rectangle                     |
| `->trim($threshold?)`      | Auto-trim whitespace                      |
| `->rotate($degrees)`       | Rotate image                              |
| `->flip($direction)`       | Flip image                                |
| `->autoOrient()`           | Auto-fix EXIF orientation                 |
| `->blur($sigma)`           | Apply Gaussian blur                       |
| `->sharpen($amount)`       | Apply sharpening                          |
| `->brightness($value)`     | Adjust brightness                         |
| `->contrast($value)`       | Adjust contrast                           |
| `->saturation($value)`     | Adjust saturation                         |
| `->grayscale()`            | Convert to grayscale                      |
| `->canvas($size)`          | Set square canvas size                    |
| `->pad($px)`               | Set canvas padding                        |
| `->background($color)`     | Set background color                      |
| `->removeBg()`             | AI background removal                    |
| `->ecommerce()`            | Flag as e-commerce operation              |
| `->ecommerceVariant(...)`  | Shorthand for e-commerce variant          |
| `->watermark(...)`         | Add image watermark                       |
| `->preset($name)`          | Apply a config preset                     |
| `->batch($sizes, $fmt?)`  | Generate multiple sizes                   |
| `->generate()`             | Execute and return `VariantDto`           |
| `->toArray()`              | Debug: get params as array                |

---

## 📄 License

MIT License. See [LICENSE](LICENSE) for details.

---

## 🤝 Contributing

Pull requests are welcome! Please open an issue first to discuss what you'd like to change.

---

**Built with ❤️ for Laravel developers by [tekkenking](https://github.com/tekkenking).**
