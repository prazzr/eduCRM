<?php
require_once '../../app/bootstrap.php';


requireLogin();

$documentService = new \EduCRM\Services\DocumentService($pdo);

// Get entity info from query params
$entityType = $_GET['entity_type'] ?? 'general';
$entityId = $_GET['entity_id'] ?? null;

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    try {
        $category = $_POST['category'] ?? null;
        $description = $_POST['description'] ?? null;
        $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

        $documentId = $documentService->uploadDocument(
            $_FILES['document'],
            $entityType,
            $entityId,
            $category,
            $description
        );

        // Update expiry date if provided
        if ($documentId && $expiryDate) {
            $expiryService = new \EduCRM\Services\DocumentExpiryService($pdo);
            $expiryService->updateExpiryDate($documentId, $expiryDate);
        }

        if ($documentId) {
            $_SESSION['flash_message'] = 'Document uploaded successfully';
            $_SESSION['flash_type'] = 'success';

            // Redirect back to entity or document list
            if ($entityId) {
                redirectWithAlert("list.php?entity_type=$entityType&entity_id=$entityId", "Document uploaded successfully", "success");
            } else {
                redirectWithAlert("list.php", "Document uploaded successfully", "success");
            }
        }
    } catch (Exception $e) {
        $errorUrl = "upload.php";
        if ($entityId) {
            $errorUrl .= "?entity_type=$entityType&entity_id=$entityId";
        }
        redirectWithAlert($errorUrl, 'Unable to upload document. Please check the file and try again.', 'error');
    }
}

$pageDetails = ['title' => 'Upload Document'];
require_once '../../templates/header.php';

$categories = $documentService->getCategories();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Upload Document</h1>
    <p class="text-slate-600 mt-1">Upload files securely to the system</p>
</div>

<?php renderFlashMessage(); ?>

<div class="max-w-2xl">
    <form method="POST" enctype="multipart/form-data" id="uploadForm"
        class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">

        <!-- Drag & Drop Upload Area -->
        <div id="dropZone"
            class="border-2 border-dashed border-slate-300 rounded-xl p-8 text-center hover:border-primary-500 transition-colors cursor-pointer mb-6">
            <svg class="w-16 h-16 mx-auto text-slate-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
            </svg>
            <p class="text-lg font-medium text-slate-700 mb-2">Drag & drop your file here</p>
            <p class="text-sm text-slate-500 mb-4">or click to browse</p>
            <input type="file" name="document" id="fileInput" class="hidden" required>
            <button type="button" onclick="document.getElementById('fileInput').click()" class="btn px-6 py-2">
                Choose File
            </button>
        </div>

        <!-- File Preview -->
        <div id="filePreview" class="hidden mb-6 p-4 bg-slate-50 rounded-lg border border-slate-200">
            <div class="flex items-center gap-4">
                <div id="fileIcon" class="text-4xl"></div>
                <div class="flex-1">
                    <p id="fileName" class="font-medium text-slate-800"></p>
                    <p id="fileSize" class="text-sm text-slate-500"></p>
                </div>
                <button type="button" onclick="clearFile()" class="text-red-600 hover:text-red-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <div id="progressBar" class="hidden mt-4">
                <div class="w-full bg-slate-200 rounded-full h-2">
                    <div id="progressFill" class="bg-primary-600 h-2 rounded-full transition-all duration-300"
                        style="width: 0%"></div>
                </div>
                <p id="progressText" class="text-sm text-slate-600 mt-1">Uploading...</p>
            </div>
        </div>

        <!-- Category Selection -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-2">Category *</label>
            <select name="category" required
                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                <option value="">Select category...</option>
                <?php foreach ($categories as $key => $label): ?>
                    <option value="<?php echo $key; ?>">
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Description -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-2">Description (Optional)</label>
            <textarea name="description" rows="3"
                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                placeholder="Add notes about this document..."></textarea>
        </div>

        <!-- Expiry Date (Phase 1 Feature) -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-slate-700 mb-2">
                Expiry Date (Optional)
                <span class="font-normal text-slate-500">— for passports, visas, etc.</span>
            </label>
            <div class="flex items-center gap-3">
                <input type="date" name="expiry_date"
                    class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    min="<?php echo date('Y-m-d'); ?>">
                <span class="text-sm text-slate-500">📅 Leave blank if not applicable</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">You'll receive alerts 30, 14, and 7 days before expiry.</p>
        </div>

        <!-- File Requirements -->
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-sm font-medium text-blue-800 mb-2">📋 File Requirements:</p>
            <ul class="text-sm text-blue-700 space-y-1">
                <li>• Maximum file size: 10MB</li>
                <li>• Allowed formats: PDF, DOC, DOCX, JPG, PNG, XLS, XLSX</li>
                <li>• Files are securely stored and encrypted</li>
            </ul>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-3">
            <button type="submit" class="btn px-6 py-2">
                Upload Document
            </button>
            <a href="list.php<?php echo $entityId ? "?entity_type=$entityType&entity_id=$entityId" : ''; ?>"
                class="btn-secondary px-6 py-2 rounded-lg font-medium">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const filePreview = document.getElementById('filePreview');
    const uploadForm = document.getElementById('uploadForm');

    // File icons by type
    const fileIcons = {
        'pdf': '📄',
        'doc': '📝',
        'docx': '📝',
        'jpg': '🖼️',
        'jpeg': '🖼️',
        'png': '🖼️',
        'xls': '📊',
        'xlsx': '📊',
        'default': '📎'
    };

    // Drag and drop handlers
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-primary-500', 'bg-primary-50');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-primary-500', 'bg-primary-50');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-primary-500', 'bg-primary-50');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            showFilePreview(files[0]);
        }
    });

    // File input change handler
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            showFilePreview(e.target.files[0]);
        }
    });

    // Show file preview
    function showFilePreview(file) {
        const extension = file.name.split('.').pop().toLowerCase();
        const icon = fileIcons[extension] || fileIcons['default'];
        const sizeMB = (file.size / 1048576).toFixed(2);

        document.getElementById('fileIcon').textContent = icon;
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = `${sizeMB} MB`;

        filePreview.classList.remove('hidden');
        dropZone.classList.add('hidden');
    }

    // Clear file
    function clearFile() {
        fileInput.value = '';
        filePreview.classList.add('hidden');
        dropZone.classList.remove('hidden');
    }

    // Form submission with progress
    uploadForm.addEventListener('submit', (e) => {
        const progressBar = document.getElementById('progressBar');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');

        progressBar.classList.remove('hidden');

        // Simulate progress (in real implementation, use XMLHttpRequest for actual progress)
        let progress = 0;
        const interval = setInterval(() => {
            progress += 10;
            progressFill.style.width = progress + '%';
            progressText.textContent = `Uploading... ${progress}%`;

            if (progress >= 90) {
                clearInterval(interval);
                progressText.textContent = 'Processing...';
            }
        }, 200);
    });
</script>

<?php require_once '../../templates/footer.php'; ?>