@extends('layouts.app')

@section('title', 'File Upload System - YoPrint')

@section('content')
<div class="container-fluid">
    <!-- Upload Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-4">
                    <!-- Upload Form -->
                    <form id="uploadForm" action="{{ route('uploads.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        
                        <!-- Drag & Drop Area -->
                        <div class="upload-area d-flex align-items-center justify-content-between p-4 mb-3" id="uploadArea">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-cloud-upload-alt fa-2x text-muted me-3"></i>
                                <div>
                                    <h6 class="mb-1">Select file/Drag and drop</h6>
                                    <small class="text-muted">Max 5 CSV files, 50MB each, 250MB total</small>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary" id="selectFilesBtn">
                                <i class="fas fa-folder-open me-2"></i>
                                Upload File
                            </button>
                            <input type="file" name="files[]" id="fileInput" multiple class="d-none" 
                                   accept=".csv,.txt">
                        </div>

                        <!-- Selected Files Preview -->
                        <div id="selectedFiles" class="mb-3" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold">Selected Files (<span id="fileCount">0</span>)</span>
                                <div>
                                    <button type="submit" class="btn btn-success btn-sm me-2" id="uploadBtn">
                                        <i class="fas fa-upload me-1"></i>Upload
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clearBtn">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </button>
                                </div>
                            </div>
                            <div id="filesList" class="row g-2"></div>
                        </div>

                        <!-- Upload Progress -->
                        <div id="uploadProgress" class="mb-3" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold">Uploading...</span>
                                <span id="progressText">0%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     id="progressBar" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Files List Section -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Recent Uploads
                        <span class="badge bg-primary ms-2" id="totalFilesCount">{{ $files->count() }}</span>
                    </h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" id="refreshBtn">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
                            <label class="form-check-label small" for="autoRefresh">Auto-refresh</label>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    @if($files->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="filesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                            </div>
                                        </th>
                                        <th width="150">Time</th>
                                        <th>File Name</th>
                                        <th width="120">Size</th>
                                        <th width="120">Status</th>
                                        <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="filesTableBody">
                                    @foreach($files as $file)
                                        <tr data-file-id="{{ $file->id }}" class="file-row">
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input file-checkbox" type="checkbox" value="{{ $file->id }}">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span>{{ $file->uploaded_at->format('M j, Y') }}</span>
                                                    <small class="text-muted">{{ $file->uploaded_at->format('g:i A') }}</small>
                                                    <small class="text-muted">({{ $file->uploaded_at->diffForHumans() }})</small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-medium">{{ $file->original_name }}</span>
                                                    <small class="text-muted">{{ $file->mime_type }}</small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">{{ $file->formatted_size }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $file->is_processed ? 'success' : 'warning' }} status-badge">
                                                    {{ $file->is_processed ? 'Completed' : 'Processing' }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('uploads.download', $file) }}" 
                                                       class="btn btn-outline-success" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger delete-file-btn" 
                                                            data-file-id="{{ $file->id }}" 
                                                            data-file-name="{{ $file->original_name }}" 
                                                            title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5" id="emptyState">
                            <i class="fas fa-cloud-upload-alt fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No files uploaded yet</h5>
                            <p class="text-muted">Upload your first files using the area above</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Bar (Hidden by default) -->
    <div class="position-fixed bottom-0 start-50 translate-middle-x" style="z-index: 1050;">
        <div class="card shadow-lg" id="bulkActionsBar" style="display: none;">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="fw-medium">
                        <span id="selectedCount">0</span> files selected
                    </span>
                    <button type="button" class="btn btn-sm btn-danger" id="bulkDeleteBtn">
                        <i class="fas fa-trash me-1"></i>Delete Selected
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllBtn">
                        <i class="fas fa-times me-1"></i>Deselect All
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this file?</p>
                <p class="fw-bold" id="deleteFileName"></p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete File
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    Bulk Delete Files
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <span id="bulkDeleteCount">0</span> selected file(s)?</p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="bulkDeleteForm" method="POST" action="{{ route('uploads.bulk-delete') }}" class="d-inline">
                    @csrf
                    <div id="bulkDeleteInputs"></div>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Files
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.upload-area {
    border: 2px dashed #e2e8f0;
    border-radius: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.upload-area:hover,
.upload-area.drag-over {
    border-color: var(--primary-color);
    background-color: rgba(59, 130, 246, 0.05);
}

.file-row:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.status-badge {
    font-size: 0.75rem;
}

#bulkActionsBar {
    transition: all 0.3s ease;
}

.table td {
    vertical-align: middle;
}

.file-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    color: white;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    let selectedFiles = [];
    let isUploading = false;
    let autoRefreshInterval = null;
    
    // DOM Elements
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
    const filesTableBody = $('#filesTableBody');
    const bulkActionsBar = $('#bulkActionsBar');
    const selectedCount = $('#selectedCount');
    
    // Initialize
    initializeEventHandlers();
    startAutoRefresh();
    
    function initializeEventHandlers() {
        // File upload handlers
        fileInput.on('change', function(e) {
            handleFiles(e.target.files);
        });
        
        selectFilesBtn.on('click', function() {
            fileInput.click();
        });
        
        // Make entire upload area clickable
        uploadArea.on('click', function(e) {
            if (!$(e.target).closest('button').length) {
                fileInput.click();
            }
        });
        
        // Drag and drop
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
        
        // Form submission
        uploadForm.on('submit', function(e) {
            e.preventDefault();
            if (selectedFiles.length === 0) {
                showNotification('Please select files to upload', 'warning');
                return;
            }
            if (isUploading) return;
            uploadFiles();
        });
        
        // Clear button
        clearBtn.on('click', function() {
            selectedFiles = [];
            fileInput.val('');
            updateFilesDisplay();
        });
        
        // File selection handlers
        $(document).on('change', '.file-checkbox', updateBulkActions);
        $('#selectAll').on('change', function() {
            $('.file-checkbox').prop('checked', $(this).prop('checked'));
            updateBulkActions();
        });
        
        // Bulk actions
        $('#deselectAllBtn').on('click', function() {
            $('.file-checkbox').prop('checked', false);
            $('#selectAll').prop('checked', false);
            updateBulkActions();
        });
        
        $('#bulkDeleteBtn').on('click', function() {
            const selectedIds = $('.file-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedIds.length === 0) return;
            
            $('#bulkDeleteCount').text(selectedIds.length);
            const inputs = selectedIds.map(id => 
                `<input type="hidden" name="file_ids[]" value="${id}">`
            ).join('');
            $('#bulkDeleteInputs').html(inputs);
            $('#bulkDeleteModal').modal('show');
        });
        
        // Single file delete
        $(document).on('click', '.delete-file-btn', function() {
            const fileId = $(this).data('file-id');
            const fileName = $(this).data('file-name');
            
            $('#deleteFileName').text(fileName);
            $('#deleteForm').attr('action', `/uploads/${fileId}`);
            $('#deleteModal').modal('show');
        });
        
        // Refresh handlers
        $('#refreshBtn').on('click', refreshFilesList);
        $('#autoRefresh').on('change', function() {
            if ($(this).prop('checked')) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });
    }
    
    function handleFiles(files) {
        const newFiles = Array.from(files);
        
        if (selectedFiles.length + newFiles.length > 5) {
            showNotification('Maximum 5 CSV files allowed per upload', 'warning');
            return;
        }
        
        newFiles.forEach(file => {
            if (validateFile(file)) {
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
        if (file.size > 50 * 1024 * 1024) {
            showNotification(`File "${file.name}" is too large. Maximum size is 50MB.`, 'error');
            return false;
        }
        
        const allowedTypes = [
            'text/csv', 'text/plain', 'application/csv'
        ];
        
        if (!allowedTypes.includes(file.type)) {
            showNotification(`File "${file.name}" is not a CSV file. Only CSV files are allowed.`, 'error');
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
        
        const totalSize = selectedFiles.reduce((sum, file) => sum + file.size, 0);
        if (totalSize > 250 * 1024 * 1024) {
            showNotification('Total file size exceeds 250MB limit', 'warning');
            return;
        }
        
        filesList.empty();
        selectedFiles.forEach((file, index) => {
            const fileCard = createFilePreview(file, index);
            filesList.append(fileCard);
        });
    }
    
    function createFilePreview(file, index) {
        const fileSize = formatBytes(file.size);
        const fileIcon = getFileIcon(file.type);
        
        return `
            <div class="col-auto">
                <div class="d-flex align-items-center gap-2 bg-light rounded p-2">
                    <div class="file-icon ${fileIcon.class}" style="width: 24px; height: 24px; font-size: 0.8rem;">
                        <i class="${fileIcon.icon}"></i>
                    </div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="text-truncate fw-medium" style="max-width: 150px;" title="${file.name}">
                            ${file.name}
                        </div>
                        <small class="text-muted">${fileSize}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
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
                showNotification('Files uploaded successfully!', 'success');
                selectedFiles = [];
                fileInput.val('');
                updateFilesDisplay();
                refreshFilesList();
            },
            error: function(xhr) {
                let errorMessage = 'Upload failed. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMessage = errors.join('\n');
                }
                showNotification(errorMessage, 'error');
            },
            complete: function() {
                isUploading = false;
                uploadBtn.prop('disabled', false);
                uploadProgress.hide();
                progressBar.css('width', '0%');
                progressText.text('0%');
            }
        });
    }
    
    function refreshFilesList() {
        const refreshBtn = $('#refreshBtn');
        const originalHtml = refreshBtn.html();
        refreshBtn.html('<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...');
        
        $.get('{{ route("uploads.index") }}')
            .done(function(response) {
                // Update the files table
                const parser = new DOMParser();
                const doc = parser.parseFromString(response, 'text/html');
                const newTableBody = $(doc).find('#filesTableBody').html();
                const newTotalCount = $(doc).find('#totalFilesCount').text();
                
                if (newTableBody) {
                    filesTableBody.html(newTableBody);
                    $('#totalFilesCount').text(newTotalCount);
                    updateBulkActions(); // Reset bulk actions
                }
                
                showNotification('Files list refreshed', 'success');
            })
            .fail(function() {
                showNotification('Failed to refresh files list', 'error');
            })
            .always(function() {
                refreshBtn.html(originalHtml);
            });
    }
    
    function startAutoRefresh() {
        stopAutoRefresh(); // Clear any existing interval
        autoRefreshInterval = setInterval(function() {
            if (!isUploading) {
                refreshFilesList();
            }
        }, 10000); // Refresh every 10 seconds
    }
    
    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
    }
    
    function updateBulkActions() {
        const checkedBoxes = $('.file-checkbox:checked');
        const count = checkedBoxes.length;
        
        if (count > 0) {
            selectedCount.text(count);
            bulkActionsBar.show();
        } else {
            bulkActionsBar.hide();
        }
        
        $('#selectAll').prop('checked', count > 0 && count === $('.file-checkbox').length);
    }
    
    function showNotification(message, type = 'info') {
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-danger' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const icon = type === 'success' ? 'fa-check-circle' : 
                    type === 'error' ? 'fa-exclamation-triangle' : 
                    type === 'warning' ? 'fa-exclamation-circle' : 'fa-info-circle';
        
        const alert = $(`
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="fas ${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('main').prepend(alert);
        setTimeout(() => alert.fadeOut(), 5000);
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