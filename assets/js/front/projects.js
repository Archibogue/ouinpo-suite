(function () {
  'use strict';

  const cfg = window.OuinpoProjects || {};

  function request(path, options) {
    const url = String(cfg.restUrl || cfg.root || '').replace(/\/$/, '') + path;
    const opts = Object.assign({
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce || ''
      }
    }, options || {});

    return fetch(url, opts).then(function (response) {
      return response.json().catch(function () {
        return {};
      }).then(function (json) {
        if (!response.ok) {
          throw new Error(json.message || (cfg.i18n && cfg.i18n.error) || 'Erreur');
        }
        return json;
      });
    });
  }

  function text(value) {
    return value == null ? '' : String(value);
  }

  function el(name, className, content) {
    const node = document.createElement(name);
    if (className) {
      node.className = className;
    }
    if (content != null) {
      node.textContent = content;
    }
    return node;
  }

  function renderTask(task, columnIndex, columnCount, canEdit) {
    const card = el('article', 'ouinpo-projects-task');
    card.dataset.taskId = String(task.id);

    const header = el('header');
    header.appendChild(el('strong', '', text(task.title)));
    header.appendChild(el('span', 'ouinpo-projects-priority ouinpo-projects-priority-' + text(task.priority), text(task.priority)));
    card.appendChild(header);

    if (task.description) {
      const desc = el('div', 'ouinpo-projects-task-description');
      desc.textContent = text(task.description).replace(/<[^>]*>/g, '');
      card.appendChild(desc);
    }

    if (task.due_date) {
      card.appendChild(el('p', 'ouinpo-projects-due', 'Echeance : ' + text(task.due_date)));
    }

    if (!canEdit) {
      return card;
    }

    const actions = el('div', 'ouinpo-projects-task-actions');
    const left = el('button', '', '<-');
    left.type = 'button';
    left.dataset.ouinpoProjectsMove = '-1';
    left.disabled = columnIndex === 0;

    const edit = el('button', '', 'Modifier');
    edit.type = 'button';
    edit.dataset.ouinpoProjectsEdit = '1';

    const del = el('button', '', 'Archiver');
    del.type = 'button';
    del.dataset.ouinpoProjectsDelete = '1';

    const right = el('button', '', '->');
    right.type = 'button';
    right.dataset.ouinpoProjectsMove = '1';
    right.disabled = columnIndex === columnCount - 1;

    actions.append(left, edit, del, right);
    card.appendChild(actions);

    return card;
  }

  function renderBoard(board, payload) {
    const target = board.querySelector('[data-ouinpo-projects-columns]');
    if (!target) {
      return;
    }

    target.innerHTML = '';
    const columns = payload.columns || [];
    const canEdit = board.dataset.canEdit === '1';

    columns.forEach(function (column, index) {
      const section = el('section', 'ouinpo-projects-column');
      section.dataset.columnId = String(column.id);
      section.appendChild(el('h3', '', text(column.title)));

      const list = el('div', 'ouinpo-projects-task-list');
      const tasks = column.tasks || [];
      if (!tasks.length) {
        list.appendChild(el('p', 'ouinpo-projects-empty', 'Rien ici.'));
      }

      tasks.forEach(function (task) {
        list.appendChild(renderTask(task, index, columns.length, canEdit));
      });

      section.appendChild(list);
      target.appendChild(section);
    });
  }

  function refreshBoard(board) {
    const projectId = board.dataset.projectId;
    return request('/projects/' + encodeURIComponent(projectId) + '/board')
      .then(function (payload) {
        renderBoard(board, payload);
      });
  }

  function bindBoard(board) {
    const form = board.querySelector('[data-ouinpo-projects-task-form]');

    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(form).entries());
        request('/projects/' + encodeURIComponent(board.dataset.projectId) + '/tasks', {
          method: 'POST',
          body: JSON.stringify(data)
        }).then(function () {
          form.reset();
          return refreshBoard(board);
        }).catch(function (error) {
          window.alert(error.message);
        });
      });
    }

    board.addEventListener('click', function (event) {
      const button = event.target.closest('button');
      if (!button) {
        return;
      }

      const task = button.closest('[data-task-id]');
      if (!task) {
        return;
      }

      const taskId = task.dataset.taskId;

      if (button.dataset.ouinpoProjectsEdit) {
        const current = task.querySelector('strong');
        const nextTitle = window.prompt((cfg.i18n && cfg.i18n.editTitle) || 'Titre', current ? current.textContent : '');
        if (!nextTitle) {
          return;
        }

        request('/tasks/' + encodeURIComponent(taskId), {
          method: 'PATCH',
          body: JSON.stringify({ title: nextTitle })
        }).then(function () {
          return refreshBoard(board);
        }).catch(function (error) {
          window.alert(error.message);
        });
      }

      if (button.dataset.ouinpoProjectsDelete) {
        if (!window.confirm((cfg.i18n && cfg.i18n.confirmDelete) || 'Archiver ?')) {
          return;
        }

        request('/tasks/' + encodeURIComponent(taskId), {
          method: 'DELETE'
        }).then(function () {
          return refreshBoard(board);
        }).catch(function (error) {
          window.alert(error.message);
        });
      }

      if (button.dataset.ouinpoProjectsMove) {
        const delta = parseInt(button.dataset.ouinpoProjectsMove, 10);
        const columns = Array.from(board.querySelectorAll('[data-column-id]'));
        const currentColumn = button.closest('[data-column-id]');
        const index = columns.indexOf(currentColumn);
        const target = columns[index + delta];
        if (!target) {
          return;
        }

        request('/tasks/' + encodeURIComponent(taskId) + '/move', {
          method: 'PATCH',
          body: JSON.stringify({
            column_id: target.dataset.columnId,
            position: target.querySelectorAll('[data-task-id]').length + 1
          })
        }).then(function () {
          return refreshBoard(board);
        }).catch(function (error) {
          window.alert(error.message);
        });
      }
    });
  }

  function bindJournal(journal) {
    const form = journal.querySelector('[data-ouinpo-projects-journal-form]');
    if (!form) {
      return;
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      const data = Object.fromEntries(new FormData(form).entries());
      request('/projects/' + encodeURIComponent(journal.dataset.projectId) + '/logs', {
        method: 'POST',
        body: JSON.stringify(data)
      }).then(function () {
        window.location.reload();
      }).catch(function (error) {
        window.alert(error.message);
      });
    });
  }

  function bindDeliverables(root) {
    const form = root.querySelector('[data-ouinpo-projects-deliverable-form]');

    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(form).entries());
        request('/projects/' + encodeURIComponent(root.dataset.projectId) + '/deliverables', {
          method: 'POST',
          body: JSON.stringify(data)
        }).then(function () {
          window.location.reload();
        }).catch(function (error) {
          window.alert(error.message);
        });
      });
    }

    root.addEventListener('click', function (event) {
      const statusButton = event.target.closest('[data-ouinpo-projects-deliverable-status]');
      const deleteButton = event.target.closest('[data-ouinpo-projects-deliverable-delete]');
      const row = event.target.closest('[data-deliverable-id]');

      if (!row || (!statusButton && !deleteButton)) {
        return;
      }

      const id = row.dataset.deliverableId;

      if (statusButton) {
        request('/deliverables/' + encodeURIComponent(id) + '/status', {
          method: 'PATCH',
          body: JSON.stringify({ status: statusButton.dataset.ouinpoProjectsDeliverableStatus })
        }).then(function () {
          window.location.reload();
        }).catch(function (error) {
          window.alert(error.message);
        });
      }

      if (deleteButton) {
        if (!window.confirm('Supprimer ce livrable ?')) {
          return;
        }
        request('/deliverables/' + encodeURIComponent(id), {
          method: 'DELETE'
        }).then(function () {
          window.location.reload();
        }).catch(function (error) {
          window.alert(error.message);
        });
      }
    });
  }

  function bindEvidence(root) {
    const form = root.querySelector('[data-ouinpo-projects-evidence-form]');

    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(form).entries());
        request('/projects/' + encodeURIComponent(root.dataset.projectId) + '/evidence', {
          method: 'POST',
          body: JSON.stringify(data)
        }).then(function () {
          window.location.reload();
        }).catch(function (error) {
          window.alert(error.message);
        });
      });
    }

    root.addEventListener('click', function (event) {
      const button = event.target.closest('[data-ouinpo-projects-evidence-delete]');
      const card = event.target.closest('[data-evidence-id]');
      if (!button || !card) {
        return;
      }

      if (!window.confirm('Supprimer cette trace ?')) {
        return;
      }

      request('/evidence/' + encodeURIComponent(card.dataset.evidenceId), {
        method: 'DELETE'
      }).then(function () {
        window.location.reload();
      }).catch(function (error) {
        window.alert(error.message);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-ouinpo-projects-board]').forEach(bindBoard);
    document.querySelectorAll('[data-ouinpo-projects-journal]').forEach(bindJournal);
    document.querySelectorAll('[data-ouinpo-projects-deliverables]').forEach(bindDeliverables);
    document.querySelectorAll('[data-ouinpo-projects-evidence]').forEach(bindEvidence);
    document.querySelectorAll('[data-ouinpo-projects-print]').forEach(function (button) {
      button.addEventListener('click', function () {
        window.print();
      });
    });
  });
}());
