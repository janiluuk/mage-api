@extends('layouts.admin')

@section('title', 'File Browser')

@php
    $pageTitle = 'File Browser';
@endphp

@section('breadcrumbs')
<x-breadcrumbs :items="[
    ['label' => 'Admin', 'url' => route('admin.files')],
    ['label' => 'File Browser']
]" />
@endsection

@section('content')
<h1>File Browser</h1>
<p>Browse user storage in a tree view and adjust per-user quotas.</p>

<div class="panel" id="user-panel">
    <div class="status info show" id="loading-status">Loading users...</div>
</div>
@endsection

@push('scripts')
<script>
    const panel = document.getElementById('user-panel');
    const loadingStatus = document.getElementById('loading-status');
    const apiBase = '/api/administration/files';

    const formatBytes = (bytes) => {
        if (bytes === 0) return '0 B';
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        const value = bytes / Math.pow(1024, i);
        return `${value.toFixed(value >= 10 ? 1 : 2)} ${sizes[i]}`;
    };

    const gbFromBytes = (bytes) => (bytes / (1024 * 1024 * 1024)).toFixed(2);
    const bytesFromGb = (gb) => Math.round(parseFloat(gb) * 1024 * 1024 * 1024);

    const fetchJson = async (url, options = {}) => {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {})
            },
            credentials: 'same-origin',
            ...options,
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message || 'Request failed');
        }

        return response.json();
    };

    const renderUser = (user) => {
        const details = document.createElement('details');
        const summary = document.createElement('summary');
        summary.textContent = `${user.login || user.email || 'User'} (ID: ${user.id})`;
        details.appendChild(summary);

        const meta = document.createElement('div');
        meta.className = 'user-meta';

        const usedBadge = document.createElement('span');
        usedBadge.className = 'badge';
        usedBadge.textContent = `Used: ${formatBytes(user.storage_used || 0)}`;
        meta.appendChild(usedBadge);

        const limitBadge = document.createElement('span');
        limitBadge.className = 'badge';
        limitBadge.textContent = `Limit: ${formatBytes(user.storage_limit || 0)}`;
        meta.appendChild(limitBadge);

        const remainingBadge = document.createElement('span');
        remainingBadge.className = 'badge';
        remainingBadge.textContent = `Remaining: ${formatBytes(user.storage_remaining || 0)}`;
        meta.appendChild(remainingBadge);

        const quotaControls = document.createElement('div');
        quotaControls.className = 'quota-controls';

        const quotaInput = document.createElement('input');
        quotaInput.type = 'number';
        quotaInput.step = '0.1';
        quotaInput.min = '0';
        quotaInput.value = gbFromBytes(user.storage_limit || 0);
        quotaControls.appendChild(quotaInput);

        const quotaLabel = document.createElement('span');
        quotaLabel.textContent = 'GB quota';
        quotaControls.appendChild(quotaLabel);

        const quotaButton = document.createElement('button');
        quotaButton.textContent = 'Save quota';
        quotaButton.className = 'secondary';
        quotaControls.appendChild(quotaButton);

        const quotaStatus = document.createElement('span');
        quotaStatus.className = 'status';
        quotaControls.appendChild(quotaStatus);

        quotaButton.addEventListener('click', async () => {
            quotaButton.disabled = true;
            quotaStatus.textContent = 'Saving...';
            quotaStatus.classList.remove('error');
            quotaStatus.classList.add('info', 'show');
            try {
                const quotaBytes = bytesFromGb(quotaInput.value || '0');
                const result = await fetchJson(`${apiBase}/users/${user.id}/quota`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ quota_bytes: quotaBytes }),
                });
                user.storage_limit = result.quota_bytes;
                limitBadge.textContent = `Limit: ${formatBytes(user.storage_limit || 0)}`;
                remainingBadge.textContent = `Remaining: ${formatBytes(Math.max(0, user.storage_limit - (user.storage_used || 0)))}`;
                quotaStatus.className = 'status success show';
                quotaStatus.textContent = 'Saved';
            } catch (error) {
                quotaStatus.className = 'status error show';
                quotaStatus.textContent = error.message;
            } finally {
                quotaButton.disabled = false;
                setTimeout(() => {
                    quotaStatus.classList.remove('show');
                    quotaStatus.textContent = '';
                }, 3000);
            }
        });

        meta.appendChild(quotaControls);
        details.appendChild(meta);

        const tree = document.createElement('div');
        tree.className = 'tree';
        tree.textContent = 'Expand to load files.';
        details.appendChild(tree);

        details.addEventListener('toggle', async () => {
            if (!details.open || details.dataset.loaded === 'true') {
                return;
            }
            tree.textContent = 'Loading files...';
            try {
                const payload = await fetchJson(`${apiBase}/users/${user.id}`);
                tree.innerHTML = '';

                const grouped = payload.files.reduce((acc, file) => {
                    const key = file.project_id || 'Unassigned';
                    acc[key] = acc[key] || [];
                    acc[key].push(file);
                    return acc;
                }, {});

                const list = document.createElement('ul');
                Object.entries(grouped).forEach(([project, files]) => {
                    const projectItem = document.createElement('li');
                    projectItem.innerHTML = `<strong>${project}</strong>`;
                    const fileList = document.createElement('ul');
                    files.forEach((file) => {
                        const fileItem = document.createElement('li');
                        fileItem.textContent = `${file.original_name} (${file.type}, ${formatBytes(file.size)})`;
                        fileList.appendChild(fileItem);
                    });
                    projectItem.appendChild(fileList);
                    list.appendChild(projectItem);
                });

                if (!payload.files.length) {
                    tree.textContent = 'No files uploaded.';
                } else {
                    tree.appendChild(list);
                }
                details.dataset.loaded = 'true';
            } catch (error) {
                tree.textContent = error.message;
                tree.classList.add('error');
            }
        });

        return details;
    };

    const loadUsers = async () => {
        try {
            const payload = await fetchJson(`${apiBase}/overview?per_page=50`);
            loadingStatus.remove();
            panel.innerHTML = '';
            payload.data.forEach((user) => {
                panel.appendChild(renderUser(user));
            });
        } catch (error) {
            loadingStatus.className = 'status error show';
            loadingStatus.textContent = error.message;
        }
    };

    loadUsers();
</script>
@endpush
