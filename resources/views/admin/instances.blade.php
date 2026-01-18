<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mage Admin - Instance Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Inter", "Segoe UI", sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            margin: 0;
            padding: 0;
        }
        .header {
            background: #1e293b;
            border-bottom: 1px solid #334155;
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 24px;
            margin: 0;
        }
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .container {
            padding: 32px;
        }
        .section {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #f1f5f9;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #334155;
        }
        .table th {
            color: #94a3b8;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
        }
        .table td {
            color: #e2e8f0;
        }
        .table tr:hover {
            background: #0f172a;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-enabled {
            background: #065f46;
            color: #6ee7b7;
        }
        .badge-disabled {
            background: #7f1d1d;
            color: #fca5a5;
        }
        .badge-type {
            background: #1e3a8a;
            color: #93c5fd;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-secondary {
            background: #475569;
            color: white;
        }
        .btn-secondary:hover {
            background: #334155;
        }
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-group {
            display: flex;
            gap: 8px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            color: #e2e8f0;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #f1f5f9;
            font-size: 14px;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 24px;
            width: 90%;
            max-width: 500px;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h3 {
            font-size: 20px;
            color: #f1f5f9;
        }
        .close-btn {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .close-btn:hover {
            color: #f1f5f9;
        }
        .status-message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
            display: none;
        }
        .status-message.success {
            background: #065f46;
            color: #6ee7b7;
            display: block;
        }
        .status-message.error {
            background: #7f1d1d;
            color: #fca5a5;
            display: block;
        }
        .loading {
            text-align: center;
            padding: 20px;
            color: #94a3b8;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        .logout-btn {
            background: #dc2626;
            color: white;
        }
        .logout-btn:hover {
            background: #b91c1c;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Mage Admin - Instance Management</h1>
        <div class="header-actions">
            <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-danger logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>ComfyUI & Stable Diffusion Instances</h2>
                <button class="btn btn-primary" onclick="openAddModal()">+ Add Instance</button>
            </div>

            <div id="status-message" class="status-message"></div>

            <div id="instances-container">
                <div class="loading">Loading instances...</div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="instance-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Add Instance</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="instance-form">
                <input type="hidden" id="instance-id" name="id">
                
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="url">URL *</label>
                    <input type="url" id="url" name="url" required placeholder="https://example.com">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="type">Type *</label>
                        <select id="type" name="type" required>
                            <option value="">Select type</option>
                            <option value="comfyui">ComfyUI</option>
                            <option value="stable_diffusion_forge">Stable Diffusion Forge</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="enabled">Status</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="enabled" name="enabled" value="1">
                            <label for="enabled" style="margin: 0; cursor: pointer;">Enabled</label>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Save</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const apiBase = '/api/administration/generator-instances';
        let instances = [];

        const showMessage = (message, type = 'success') => {
            const msgEl = document.getElementById('status-message');
            msgEl.textContent = message;
            msgEl.className = `status-message ${type}`;
            setTimeout(() => {
                msgEl.className = 'status-message';
            }, 5000);
        };

        const fetchInstances = async () => {
            try {
                // Fetch comprehensive status with metrics
                const statusResponse = await fetch('/api/administration/instances/status', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!statusResponse.ok) {
                    // Fallback to basic instances endpoint
                    const response = await fetch(apiBase, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) throw new Error('Failed to load instances');
                    instances = await response.json();
                } else {
                    const statusData = await statusResponse.json();
                    instances = statusData.instances || [];
                    window.ffmpegStatus = statusData.ffmpeg;
                }
                
                renderInstances();
            } catch (error) {
                document.getElementById('instances-container').innerHTML = 
                    `<div class="empty-state">Error loading instances: ${error.message}</div>`;
            }
        };

        const renderInstances = () => {
            const container = document.getElementById('instances-container');
            
            if (instances.length === 0) {
                container.innerHTML = '<div class="empty-state">No instances found. Click "Add Instance" to create one.</div>';
                return;
            }

            container.innerHTML = `
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>URL</th>
                            <th>Queue</th>
                            <th>Processing</th>
                            <th>GPU/CPU/Mem</th>
                            <th>Model</th>
                            <th>Health</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${instances.map(instance => `
                            <tr>
                                <td>${escapeHtml(instance.name)}</td>
                                <td><span class="badge badge-type">${escapeHtml(instance.type)}</span></td>
                                <td><a href="${escapeHtml(instance.url)}" target="_blank" style="color: #60a5fa;">${escapeHtml(instance.url)}</a></td>
                                <td>${instance.queue_size || 0}</td>
                                <td>${instance.processing_count || 0}</td>
                                <td>
                                    ${instance.gpu_utilization !== null ? `GPU: ${instance.gpu_utilization}%<br>` : ''}
                                    ${instance.cpu_utilization !== null ? `CPU: ${instance.cpu_utilization}%<br>` : ''}
                                    ${instance.memory_utilization !== null ? `Mem: ${instance.memory_utilization}%` : 'N/A'}
                                </td>
                                <td>${instance.current_model || 'N/A'}</td>
                                <td>
                                    <span class="badge ${instance.health_status === 'online' ? 'badge-enabled' : instance.health_status === 'degraded' ? 'badge-type' : 'badge-disabled'}">
                                        ${instance.health_status || 'offline'}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge ${instance.enabled ? 'badge-enabled' : 'badge-disabled'}">
                                        ${instance.enabled ? 'Enabled' : 'Disabled'}
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-secondary btn-sm" onclick="toggleInstance(${instance.id})">
                                            ${instance.enabled ? 'Disable' : 'Enable'}
                                        </button>
                                        <button class="btn btn-primary btn-sm" onclick="editInstance(${instance.id})">Edit</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteInstance(${instance.id})">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                ${window.ffmpegStatus ? `
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #334155;">
                        <h3 style="margin-bottom: 12px;">FFMpeg Status</h3>
                        <p>Active Encoding: ${window.ffmpegStatus.active_encoding_count || 0} | Pending: ${window.ffmpegStatus.pending_encoding_count || 0} | Total Queue: ${window.ffmpegStatus.total_queue_size || 0}</p>
                    </div>
                ` : ''}
            `;
        };

        const escapeHtml = (text) => {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };

        const openAddModal = () => {
            document.getElementById('modal-title').textContent = 'Add Instance';
            document.getElementById('instance-form').reset();
            document.getElementById('instance-id').value = '';
            document.getElementById('instance-modal').classList.add('active');
        };

        const editInstance = (id) => {
            const instance = instances.find(i => i.id === id);
            if (!instance) return;

            document.getElementById('modal-title').textContent = 'Edit Instance';
            document.getElementById('instance-id').value = instance.id;
            document.getElementById('name').value = instance.name;
            document.getElementById('url').value = instance.url;
            document.getElementById('type').value = instance.type;
            document.getElementById('enabled').checked = instance.enabled;
            document.getElementById('instance-modal').classList.add('active');
        };

        const closeModal = () => {
            document.getElementById('instance-modal').classList.remove('active');
        };

        const toggleInstance = async (id) => {
            try {
                const response = await fetch(`${apiBase}/${id}/toggle`, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) throw new Error('Failed to toggle instance');
                
                showMessage('Instance status updated');
                await fetchInstances();
            } catch (error) {
                showMessage(error.message, 'error');
            }
        };

        const deleteInstance = async (id) => {
            if (!confirm('Are you sure you want to delete this instance?')) return;

            try {
                const response = await fetch(`${apiBase}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) throw new Error('Failed to delete instance');
                
                showMessage('Instance deleted');
                await fetchInstances();
            } catch (error) {
                showMessage(error.message, 'error');
            }
        };

        document.getElementById('instance-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            const id = formData.get('id');
            const data = {
                name: formData.get('name'),
                url: formData.get('url'),
                type: formData.get('type'),
                enabled: formData.get('enabled') === '1',
            };

            try {
                const url = id ? `${apiBase}/${id}` : apiBase;
                const method = id ? 'PUT' : 'POST';

                const response = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(data),
                });

                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    throw new Error(error.message || 'Failed to save instance');
                }

                showMessage(id ? 'Instance updated' : 'Instance created');
                closeModal();
                await fetchInstances();
            } catch (error) {
                showMessage(error.message, 'error');
            }
        });

        // Close modal on outside click
        document.getElementById('instance-modal').addEventListener('click', (e) => {
            if (e.target.id === 'instance-modal') {
                closeModal();
            }
        });

        // Load instances on page load
        fetchInstances();

        // Auto-refresh every 30 seconds
        setInterval(fetchInstances, 30000);
    </script>
</body>
</html>
