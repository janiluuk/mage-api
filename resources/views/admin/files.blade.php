<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mage Admin Files</title>
    <style>
        body {
            font-family: "Inter", "Segoe UI", sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            margin: 0;
            padding: 32px;
        }
        h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        p {
            color: #94a3b8;
        }
        .panel {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        details {
            background: #0b1220;
            border: 1px solid #1f2937;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 12px;
        }
        summary {
            cursor: pointer;
            font-weight: 600;
        }
        .user-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
            align-items: center;
        }
        .badge {
            background: #1e293b;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            color: #e2e8f0;
        }
        .quota-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        input[type="number"] {
            background: #0f172a;
            border: 1px solid #334155;
            color: #e2e8f0;
            padding: 6px 8px;
            border-radius: 6px;
            width: 120px;
        }
        button {
            background: #2563eb;
            border: none;
            color: #fff;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
        }
        button:disabled {
            background: #334155;
            cursor: not-allowed;
        }
        .tree {
            margin-top: 16px;
            padding-left: 12px;
        }
        .tree ul {
            list-style: none;
            margin: 8px 0 8px 12px;
            padding: 0;
            border-left: 1px solid #334155;
        }
        .tree li {
            padding: 4px 0 4px 12px;
            position: relative;
        }
        .tree li::before {
            content: "";
            position: absolute;
            left: 0;
            top: 14px;
            width: 10px;
            height: 1px;
            background: #334155;
        }
        .status {
            font-size: 12px;
            color: #fbbf24;
            margin-top: 8px;
        }
        .error {
            color: #f87171;
        }
    </style>
</head>
<body>
    <h1>Mage Admin · File Browser</h1>
    <p>Browse user storage in a tree view and adjust per-user quotas.</p>

    <div class="panel" id="user-panel">
        <div class="status" id="loading-status">Loading users...</div>
    </div>

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
            quotaControls.appendChild(quotaButton);

            const quotaStatus = document.createElement('span');
            quotaStatus.className = 'status';
            quotaControls.appendChild(quotaStatus);

            quotaButton.addEventListener('click', async () => {
                quotaButton.disabled = true;
                quotaStatus.textContent = 'Saving...';
                quotaStatus.classList.remove('error');
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
                    quotaStatus.textContent = 'Saved';
                } catch (error) {
                    quotaStatus.textContent = error.message;
                    quotaStatus.classList.add('error');
                } finally {
                    quotaButton.disabled = false;
                    setTimeout(() => quotaStatus.textContent = '', 3000);
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
                loadingStatus.textContent = error.message;
                loadingStatus.classList.add('error');
            }
        };

        loadUsers();
    </script>
</body>
</html>
