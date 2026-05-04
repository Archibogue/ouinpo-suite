(function($) {
    $(function() {
        var settings = window.OuinpoSubmissionsAdmin || {};
        var postId = $('#post_ID').val() || settings.postId;
        var ajaxUrl = settings.ajaxUrl || '';
        var nonce = settings.nonce || '';

        var box = $('#ouinpo_res_current');
        var list = $('#ouinpo_res_files_list');

        function refreshEmpty() {
            if (!box.length) return;

            var hasItems = list && list.children('li').length > 0;

            box.find('.ouinpo-empty')[hasItems ? 'hide' : 'show']();
        }

        refreshEmpty();

        function addAttachment(id, title, url) {
            id = parseInt(id, 10);

            if (!id || !list.length) return;

            title = title || ('Pièce jointe #' + id);
            url = url || '#';

            // éviter les doublons
            if (list.find('li[data-id="' + id + '"]').length) return;

            var li = $('<li/>', { 'data-id': id, class: 'ouinpo-res-file-item' });

            var link = $('<a/>', {
                href: url,
                target: '_blank',
                text: title + ' (#' + id + ')'
            });

            var removeBtn = $('<button/>', {
                type: 'button',
                class: 'button-link ouinpo-remove-file',
                text: 'Retirer'
            });

            var hidden = $('<input/>', {
                type: 'hidden',
                name: 'ouinpo_res_attachment_ids[]',
                value: id
            });

            li.append(link).append(' ').append(removeBtn).append(hidden);
            list.append(li);
            refreshEmpty();
        }

        // suppression d'un fichier
        $(document).on('click', '.ouinpo-remove-file', function(e) {
            e.preventDefault();

            $(this).closest('li').remove();
            refreshEmpty();
        });

        // Drag & drop / input file
        var dz = $('#ouinpo-dropzone');
        var fileInput = $('#ouinpo-hidden-file');

        function highlight(on) {
            dz.toggleClass('is-dragover', !!on);
        }

        dz.on('dragenter dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            highlight(true);
        });

        dz.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            highlight(false);
        });

        dz.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            highlight(false);

            var files = e.originalEvent.dataTransfer.files;

            if (files && files.length) {
                for (var i = 0; i < files.length; i++) {
                    uploadFile(files[i]);
                }
            }
        });

        dz.on('click', function() {
            fileInput.trigger('click');
        });

        fileInput.on('change', function() {
            if (this.files && this.files.length) {
                for (var i = 0; i < this.files.length; i++) {
                    uploadFile(this.files[i]);
                }

                this.value = '';
            }
        });

        function uploadFile(file) {
            if (!file) return;

            var statusLine = box.find('.ouinpo-empty');

            statusLine.text('Envoi en cours…');

            var form = new FormData();

            form.append('action', 'ouinpo_res_upload');
            form.append('nonce', nonce);
            form.append('post_id', postId);
            form.append('file', file);

            var xhr = new XMLHttpRequest();

            xhr.open('POST', ajaxUrl, true);

            xhr.onload = function() {
                try {
                    var resp = JSON.parse(xhr.responseText);

                    if (resp.success) {
                        addAttachment(resp.data.attachment_id, resp.data.title, resp.data.download_url);
                    } else {
                        var msg = (resp.data && resp.data.message) ? resp.data.message : 'Erreur inconnue';

                        alert('Échec de l\'envoi : ' + msg);
                    }
                } catch (err) {
                    alert('Réponse invalide du serveur lors de l\'envoi.');
                }

                refreshEmpty();
            };

            xhr.onerror = function() {
                alert('Erreur réseau durant l\'envoi');
                refreshEmpty();
            };

            xhr.send(form);
        }
    });
})(jQuery);
