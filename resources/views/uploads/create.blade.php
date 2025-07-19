@extends('layouts.app')

@section('title', 'Upload Files - YoPrint')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-cloud-upload-alt me-2"></i>
                    Upload Files
                </h4>
            </div>
            <div class="card-body">
                <!-- Upload Guidelines -->
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Upload Guidelines:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Maximum 10 files per upload</li>
                        <li>Each file must be under 10MB</li>
                        <li>Total upload size limit: 50MB</li>
                        <li>Supported formats: JPG, PNG, GIF, PDF, DOC, DOCX, TXT, XLS, XLSX, ZIP, RAR</li>
                    </ul>
                </div>

                <!-- Upload Form -->
                <form id="uploadForm" action="{{ route('uploads.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <!-- Drag & Drop Area -->
                    <div class="upload-area d-flex align-items-center justify-content-center flex-column p-5 mb-4" 
                         id="uploadArea">
                        <div class="text-center">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted mb-2">Drag & Drop Files Here</h5>
                            <p class="text-muted mb-3">or</p>
                            <button type="button" class="btn btn-primary" id="selectFilesBtn">
                                <i class="fas fa-folder-open me-2"></i>
                                Select Files
                            </button>
                            <input type="file" name="files[]" id="fileInput" multiple class="d-none" 
                                   accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.xls,.xlsx,.zip,.rar">
                        </div>
                    </div>

                    <!-- Selected Files Display -->
                    <div id="selectedFiles" class="mb-4" style="display: none;">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-list me-2"></i>
                            Selected Files (<span id="fileCount">0</span>)
                        </h6>
                        <div id="filesList" class="row g-3"></div>
                        
                        <!-- Upload Progress -->
                        <div id="uploadProgress" class="mt-4" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold">Upload Progress</span>
                                <span id="progressText">0%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     id="progressBar" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-4 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-success" id="uploadBtn">
                                <i class="fas fa-upload me-2"></i>
                                Upload Files
                            </button>
                            <button type="button" class="btn btn-secondary" id="clearBtn">
                                <i class="fas fa-times me-2"></i>
                                Clear All
                            </button>
                            <a href="{{ route('uploads.index') }}" class="btn btn-outline-primary">
                                <i class="fas fa-list me-2"></i>
                                View All Files
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Upload Tips -->
                <div class="row mt-5">
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="file-icon image mb-3 mx-auto">
                                <i class="fas fa-image"></i>
                            </div>
                            <h6>Images</h6>
                            <small class="text-muted">JPG, PNG, GIF formats supported</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="file-icon doc mb-3 mx-auto">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h6>Documents</h6>
                            <small class="text-muted">PDF, DOC, DOCX, TXT, XLS, XLSX</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="file-icon archive mb-3 mx-auto">
                                <i class="fas fa-file-archive"></i>
                            </div>
                            <h6>Archives</h6>
                            <small class="text-muted">ZIP, RAR formats supported</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let selectedFiles = [];
    let isUploading = false;
    
    const uploadArea = $('#uploadArea');
    const fileInput = $('#fileInput');
    const selectedFilesDiv = $('#selectedFiles');
    const filesList = $('#filesList');
    const fileCount = $('#fileCount');
    const uploadForm = $('#uploadForm');
    const uploadBtn = $('#uploadBtn');
    const clearBtn = $('#clearBtn');
    const selectFilesBtn = $('#selectFilesBtn');
    const uploadProgress = $('#uploadProgress');
    const progressBar = $('#progressBar');
    const progressText = $('#progressText');
    
    // File input change handler
    fileInput.on('change', function(e) {
        handleFiles(e.target.files);
    });
    
    // Select files button
    selectFilesBtn.on('click', function() {
        fileInput.click();
    });
    
    // Drag and drop handlers
    uploadArea.on('dragover', function(e) {
        e.preventDefault();
        uploadArea.addClass('drag-over');
    });
    
    uploadArea.on('dragleave', function(e) {
        e.preventDefault();
        uploadArea.removeClass('drag-over');
    });
    
    uploadArea.on('drop', function(e) {
        e.preventDefault();
        uploadArea.removeClass('drag-over');
        handleFiles(e.originalEvent.dataTransfer.files);
    });
    
    // Clear button
    clearBtn.on('click', function() {
        selectedFiles = [];
        fileInput.val('');
        updateFilesDisplay();
    });
    
    // Form submission
    uploadForm.on('submit', function(e) {
        e.preventDefault();
        
        if (selectedFiles.length === 0) {
            alert('Please select files to upload');
            return;
        }
        
        if (isUploading) {
            return;
        }
        
        uploadFiles();
    });
    
    function handleFiles(files) {
        const newFiles = Array.from(files);
        
        // Validate file count
        if (selectedFiles.length + newFiles.length > 10) {
            alert('Maximum 10 files allowed per upload');
            return;
        }
        
        // Validate and add files
        newFiles.forEach(file => {
            if (validateFile(file)) {
                // Check for duplicates
                const isDuplicate = selectedFiles.some(f => 
                    f.name === file.name && f.size === file.size
                );
                
                if (!isDuplicate) {
                    selectedFiles.push(file);
                }
            }
        });
        
        updateFilesDisplay();
    }
    
    function validateFile(file) {
        // Check file size (10MB limit)
        if (file.size > 10 * 1024 * 1024) {
            alert(`File "${file.name}" is too large. Maximum size is 10MB.`);
            return false;
        }
        
        // Check file type
        const allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain', 'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip', 'application/x-rar-compressed'
        ];
        
        if (!allowedTypes.includes(file.type)) {
            alert(`File "${file.name}" has an unsupported format.`);
            return false;
        }
        
        return true;
    }
    
    function updateFilesDisplay() {
        if (selectedFiles.length === 0) {
            selectedFilesDiv.hide();
            return;
        }
        
        selectedFilesDiv.show();
        fileCount.text(selectedFiles.length);
        
        // Calculate total size
        const totalSize = selectedFiles.reduce((sum, file) => sum + file.size, 0);
        
        // Check total size limit (50MB)
        if (totalSize > 50 * 1024 * 1024) {
            alert('Total file size exceeds 50MB limit');
            return;
        }
        
        // Display files
        filesList.empty();
        selectedFiles.forEach((file, index) => {
            const fileCard = createFileCard(file, index);
            filesList.append(fileCard);
        });
    }
    
    function createFileCard(file, index) {
        const fileSize = formatBytes(file.size);
        const fileIcon = getFileIcon(file.type);
        
        return `
            <div class="col-md-6 col-lg-4">
                <div class="card border">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="file-icon ${fileIcon.class} me-3">
                                <i class="${fileIcon.icon}"></i>
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <h6 class="mb-1 text-truncate" title="${file.name}">
                                    ${file.name}
                                </h6>
                                <small class="text-muted">${fileSize}</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="removeFile(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    function getFileIcon(mimeType) {
        if (mimeType.startsWith('image/')) {
            return { class: 'image', icon: 'fas fa-image' };
        } else if (mimeType === 'application/pdf') {
            return { class: 'pdf', icon: 'fas fa-file-pdf' };
        } else if (mimeType.includes('word') || mimeType.includes('document')) {
            return { class: 'doc', icon: 'fas fa-file-word' };
        } else if (mimeType.includes('excel') || mimeType.includes('sheet')) {
            return { class: 'doc', icon: 'fas fa-file-excel' };
        } else if (mimeType.includes('zip') || mimeType.includes('rar')) {
            return { class: 'archive', icon: 'fas fa-file-archive' };
        } else {
            return { class: 'default', icon: 'fas fa-file' };
        }
    }
    
    function uploadFiles() {
        isUploading = true;
        uploadBtn.prop('disabled', true);
        uploadProgress.show();
        
        const formData = new FormData();
        selectedFiles.forEach(file => {
            formData.append('files[]', file);
        });
        
        // Add CSRF token
        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
        
        $.ajax({
            url: '{{ route("uploads.store") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        progressBar.css('width', percentComplete + '%');
                        progressText.text(percentComplete + '%');
                    }
                });
                return xhr;
            },
            success: function(response) {
                window.location.href = '{{ route("uploads.index") }}';
            },
            error: function(xhr) {
                isUploading = false;
                uploadBtn.prop('disabled', false);
                uploadProgress.hide();
                
                let errorMessage = 'Upload failed. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMessage = errors.join('\n');
                }
                alert(errorMessage);
            }
        });
    }
    
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    // Global function for removing files
    window.removeFile = function(index) {
        selectedFiles.splice(index, 1);
        updateFilesDisplay();
    };
});
</script>
@endpush 