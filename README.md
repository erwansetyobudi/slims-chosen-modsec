# slims-chosen-modsec
Plugin ini merupakan eksperimen saya terkait masalah yang terjadi pada SLiMS yang sudah Online yaitu akses ke data Publisher, Publish Place dll yang terblokir oleh fitur ModSec di Cpanel.

Beberapa percobaan berhasil disebagian penyedia hosting web terkenal di Indonesia. 

## Disclaimer
Adapun apabila plugin ini tidak bekerja sesuai **ekpektasi** anda maka anda bisa berbagi cerita, informasi dll pada menu **issues** diatas. Apapun yang anda cerita selama tidak mengadung data sensitif 
akan berharga untuk keberlangsungan plugin ini agar pengguna SLiMS yang lain terbantu dan menjadi amalan baik untuk anda 😊.

Saya akan jelaskan bagaimana cara kerja plugin **slims-chosen-modsec** dalam mengatasi masalah akses ke data Publisher, Publish Place, dll yang diblokir oleh ModSec di Cpanel.

## Masalah yang Dihadapi

Di lingkungan hosting dengan **ModSecurity (ModSec)** aktif, sering terjadi pemblokiran request AJAX yang digunakan SLiMS untuk mengambil data dari database, terutama untuk field seperti:
- Publisher
- Publish Place
- Classification
- dll

Biasanya request seperti ini yang diblokir:
```
/admin/AJAX_lookup_handler.php?format=json&allowNew=true&tableName=mst_publisher&keywords=...
```

## Cara Kerja Plugin

Berdasarkan struktur file yang Anda berikan, plugin ini bekerja dengan beberapa pendekatan:

### 1. **Mengubah Endpoint AJAX Lookup**

Di kode Anda (file `index.php` yang error), saya melihat baris ini:

```php
// Pada bagian Publisher
$form->addSelectList('publisherID', __('Publisher'), $publ_options, $rec_d['publisher_id'] ?? '', 
    'class="select2" data-src="' . $_SERVER['PHP_SELF'] . '" data-src-table="mst_publisher" data-src-cols="publisher_id:publisher_name"');
```

**Cara kerjanya:**
- Plugin mengubah endpoint AJAX dari `AJAX_lookup_handler.php` menjadi **file plugin itu sendiri** (`index.php` dari plugin)
- Data dikirim ke file yang sama (self) dengan parameter khusus
- Ini menghindari deteksi ModSec karena path-nya berbeda

### 2. **Menggunakan Metode POST dengan JSON**

Di awal file `index.php` Anda, ada kode:

```php
$rawInput = false;
if (count($_POST) < 1) {
    $rawInput = true;
    $_POST = json_decode(file_get_contents('php://input'), true);
}

if (isset($_POST['tableName'])) {
    // receive json data if $_POST data empty
    
    // ... proses query database ...
    
    $sql_string = "SELECT $fields FROM $table_name WHERE $criteria LIMIT $limit";
    $query = $dbs->query($sql_string);
    
    // ... return JSON response ...
}
```

**Cara kerjanya:**
1. Plugin menerima request AJAX yang biasanya dikirim ke `AJAX_lookup_handler.php`
2. Request dikirim dalam format **JSON** (bukan form-data biasa)
3. ModSec lebih jarang memblokir request JSON karena mirip dengan API request
4. Plugin memproses langsung query ke database
5. Mengembalikan data dalam format JSON yang diharapkan Select2

### 3. **Struktur Komunikasi**

```javascript
// Di frontend (Select2), request dikirim ke:
data-src="index.php"  // Bukan AJAX_lookup_handler.php

// Data dikirim sebagai JSON:
{
    "tableName": "mst_publisher",
    "tableFields": "publisher_id:publisher_name",
    "keywords": "Gramedia"
}

// Plugin menerima dan memproses:
- Mengecek apakah ada parameter 'tableName'
- Membangun query SELECT
- Mengembalikan: [{"id": 1, "text": "Gramedia"}, ...]
```

### 4. **Fitur "Add New" yang Cerdas**

Plugin juga mendukung penambahan data baru langsung dari dropdown:

```php
// Jika user mengetik data baru
if (stripos($_POST['publisherID'], 'NEW:') === 0) {
    $new_publisher = str_ireplace('NEW:', '', trim(strip_tags($_POST['publisherID'])));
    $new_id = utility::getID($dbs, 'mst_publisher', 'publisher_id', 'publisher_name', $new_publisher);
    $data['publisher_id'] = $new_id;
}
```

**Cara kerjanya:**
- Jika user mengetik nama publisher baru (diawali "NEW:")
- Plugin akan otomatis membuat entri baru di database
- Mengembalikan ID yang baru dibuat

## Diagram Alur Kerja Plugin

```
┌─────────────────────────────────────────────────────────────┐
│ 1. User mengisi form bibliografi                            │
│    - Mengetik nama publisher di dropdown Select2            │
└─────────────────┬───────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. Select2 mengirim AJAX request ke plugin (bukan handler) │
│    POST /plugins/slims-chosen-modsec/index.php              │
│    Content-Type: application/json                          │
│    Body: { tableName, tableFields, keywords }              │
└─────────────────┬───────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. Plugin menerima request                                  │
│    - Mengecek ada POST data?                                │
│    - Jika tidak, baca dari php://input (JSON)              │
│    - Deteksi parameter 'tableName'                         │
└─────────────────┬───────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. Plugin memproses query                                   │
│    - Escape input untuk keamanan                            │
│    - Bangun query SELECT                                    │
│    - Eksekusi ke database                                   │
└─────────────────┬───────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. Format response JSON                                     │
│    [                                                       │
│      {"id": 1, "text": "Gramedia Pustaka Utama"},         │
│      {"id": 2, "text": "Erlangga"},                       │
│      {"id": "NEW:Indie Publisher", "text": "Indie Pub..."}│
│    ]                                                       │
└─────────────────┬───────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────────────┐
│ 6. Select2 menampilkan hasil                                │
│    - User bisa memilih existing atau menambah baru         │
└─────────────────────────────────────────────────────────────┘
```

## Mengapa Ini Bisa Melewati ModSec?

1. **URL Berbeda**: ModSec biasanya dikonfigurasi untuk memblokir pola URL tertentu seperti `AJAX_lookup_handler.php`

2. **Content-Type JSON**: ModSec lebih longgar terhadap request dengan `Content-Type: application/json`

3. **Payload Terenkapsulasi**: Data dikirim dalam JSON yang lebih sulit dideteksi sebagai pola serangan

4. **Metode POST dengan Body**: ModSec lebih ketat pada GET parameter daripada POST body

## Kode Lengkap Plugin yang Relevan

Dari file Anda, bagian yang menangani AJAX lookup:

```php
// Deteksi jika ini adalah request AJAX lookup
if (isset($_POST['tableName'])) {
    $limit = 20;
    $table_name = $dbs->escape_string(trim($_POST['tableName']));
    $table_fields = trim($_POST['tableFields']);
    
    if (isset($_POST['keywords']) and !empty($_POST['keywords'])) {
        $keywords = $dbs->escape_string(urldecode(ltrim($_POST['keywords'])));
    }
    
    // Bangun query
    $fields = str_replace(':', ', ', $table_fields);
    $criteria = '';
    foreach (explode(':', $table_fields) as $field) {
        $criteria .= " $field LIKE '%$keywords%' OR";
    }
    $criteria = substr_replace($criteria, '', -2);
    
    $sql_string = "SELECT $fields FROM $table_name WHERE $criteria LIMIT $limit";
    $query = $dbs->query($sql_string);
    
    // Format response untuk Select2
    $data = array();
    while ($row = $query->fetch_row()) {
        $data[] = array(
            'id' => $row[0], 
            'text' => $row[1] . (isset($row[2]) ? ' - ' . $row[2] : '')
        );
    }
    
    // Jika tidak ditemukan, beri opsi untuk menambah baru
    if (count($data) == 0) {
        $data[] = array(
            'id' => 'NEW:'.$keywords, 
            'text' => $keywords.' &lt;'.__('Add New').'&gt;'
        );
    }
    
    echo json_encode($data);
    exit;
}
```

## Kelebihan Pendekatan Ini

1. **Kompatibel dengan ModSec**: Tidak memicu aturan ModSec
2. **Self-contained**: Tidak bergantung pada file lain
3. **Mendukung "Add New"**: User bisa menambah data baru langsung
4. **Responsif**: Menggunakan format JSON yang ringan

## Kekurangan

1. **Duplikasi Kode**: Query yang sama mungkin ada di beberapa tempat
2. **Keamanan**: Perlu memastikan input tervalidasi dengan baik
3. **Maintenance**: Jika struktur database berubah, plugin perlu diupdate

Plugin ini adalah solusi cerdas untuk masalah umum di hosting dengan ModSec aktif, dengan mengalihkan request AJAX ke endpoint alternatif dan menggunakan format JSON yang lebih "ramah" terhadap security module.
