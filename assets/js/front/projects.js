(function () {
  'use strict';

  const cfg = window.OuinpoProjects || {};

  function request(path, options) {
    if (!cfg.nonce) {
      return Promise.reject(new Error('Session WordPress expiree ou nonce REST absent.'));
    }

    const root = String(cfg.restUrl || cfg.root || '').replace(/\/$/, '');
    if (!root) {
      return Promise.reject(new Error('Endpoint REST Projects indisponible.'));
    }

    const url = root + path;
    const isFormData = options && options.body && window.FormData && options.body instanceof FormData;
    const opts = Object.assign({
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce || ''
      }
    }, options || {});

    if (isFormData) {
      opts.headers = {
        'X-WP-Nonce': cfg.nonce || ''
      };
    }

    return fetch(url, opts).then(function (response) {
      return response.json().catch(function () {
        return {};
      }).then(function (json) {
        if (!response.ok) {
          const fallback = response.status === 403
            ? 'Acces refuse ou session expiree.'
            : (response.status === 429 ? 'Quota IA atteint. Reessaie plus tard.' : ((cfg.i18n && cfg.i18n.error) || 'Erreur serveur.'));
          throw new Error(json.message || fallback);
        }
        return json;
      });
    }).catch(function (error) {
      throw new Error(error.message || 'Erreur reseau pendant l appel REST.');
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

  function copyText(value) {
    const content = text(value);
    if (!content) {
      return Promise.reject(new Error('Aucun contenu a copier.'));
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(content);
    }

    return new Promise(function (resolve, reject) {
      const textarea = document.createElement('textarea');
      textarea.value = content;
      textarea.setAttribute('readonly', 'readonly');
      textarea.style.position = 'fixed';
      textarea.style.left = '-9999px';
      textarea.style.top = '0';
      document.body.appendChild(textarea);
      textarea.focus();
      textarea.select();
      try {
        if (document.execCommand('copy')) {
          resolve();
        } else {
          reject(new Error('Copie automatique indisponible.'));
        }
      } catch (error) {
        reject(error);
      } finally {
        document.body.removeChild(textarea);
      }
    });
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

    if (!canEdit || task.can_edit === false) {
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
        const formData = new FormData(form);
        const file = form.querySelector('input[type="file"][name="file"]');
        const hasFile = file && file.files && file.files.length > 0;
        const path = '/projects/' + encodeURIComponent(root.dataset.projectId) + (hasFile ? '/evidence/upload' : '/evidence');
        const payload = hasFile ? formData : JSON.stringify(Object.fromEntries(formData.entries()));

        request(path, {
          method: 'POST',
          body: payload
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

  function bindExports(root) {
    const button = root.querySelector('[data-ouinpo-projects-copy-markdown]');
    const output = root.querySelector('[data-ouinpo-projects-export-output]');
    if (!button || !output) {
      return;
    }

    button.addEventListener('click', function () {
      const kind = root.dataset.exportKind === 'bts-situation' ? 'bts-situation/markdown' : 'export/markdown';
      request('/projects/' + encodeURIComponent(root.dataset.projectId) + '/' + kind)
        .then(function (payload) {
          output.hidden = false;
          output.value = text(payload.content);
          output.focus();
          output.select();

          copyText(output.value).catch(function () {
            window.alert('Markdown affiche : copie manuelle possible.');
          });
        })
        .catch(function (error) {
          window.alert(error.message);
        });
    });
  }

  function aiTitleForItem(item, kind) {
    if (item.title) {
      return text(item.title);
    }
    if (kind === 'suggest_competencies') {
      return text(item.object_type) + ' #' + text(item.object_id) + ' -> competence #' + text(item.competency_id);
    }
    if (item.level && item.explanation) {
      return text(item.level) + ' - ' + text(item.title || item.explanation).slice(0, 120);
    }
    return 'Proposition';
  }

  function aiDescriptionForItem(item) {
    return text(item.reason || item.description || item.explanation || item.suggested_action || item.confidence || '');
  }

  function renderAiList(root, payload, key, canApply) {
    const preview = root.querySelector('[data-ouinpo-projects-ai-preview]');
    const apply = root.querySelector('[data-ouinpo-projects-ai-apply]');
    const items = Array.isArray(payload[key]) ? payload[key] : [];
    preview.innerHTML = '';

    const warnings = Array.isArray(payload.warnings) ? payload.warnings : [];
    if (warnings.length) {
      const warningBox = el('div', 'ouinpo-projects-ai-warning');
      warnings.forEach(function (warning) {
        warningBox.appendChild(el('p', '', text(warning)));
      });
      preview.appendChild(warningBox);
    }

    if (!items.length) {
      preview.appendChild(el('p', 'ouinpo-projects-empty', 'Aucune proposition exploitable.'));
      apply.hidden = true;
      root._ouinpoProjectsAiPayload = null;
      return;
    }

    const list = el('div', 'ouinpo-projects-ai-items');
    items.forEach(function (item, index) {
      const label = el('label', 'ouinpo-projects-ai-item');
      if (canApply) {
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = true;
        checkbox.dataset.index = String(index);
        label.appendChild(checkbox);
      }

      const body = el('span', 'ouinpo-projects-ai-item-body');
      body.appendChild(el('strong', '', aiTitleForItem(item, payload.kind)));
      const desc = aiDescriptionForItem(item);
      if (desc) {
        body.appendChild(el('span', '', desc));
      }
      label.appendChild(body);
      list.appendChild(label);
    });

    preview.appendChild(list);
    root._ouinpoProjectsAiPayload = { kind: payload.kind, items: items };
    apply.hidden = !canApply;
  }

  function renderAiSummary(root, payload) {
    const preview = root.querySelector('[data-ouinpo-projects-ai-preview]');
    const apply = root.querySelector('[data-ouinpo-projects-ai-apply]');
    preview.innerHTML = '';
    root._ouinpoProjectsAiPayload = null;
    apply.hidden = true;

    const keys = Object.keys(payload).filter(function (key) {
      return ['kind', 'project_id', 'ai_notice'].indexOf(key) === -1;
    });

    keys.forEach(function (key) {
      const value = payload[key];
      const section = el('section', 'ouinpo-projects-ai-summary-section');
      section.appendChild(el('h3', '', key.replace(/_/g, ' ')));
      if (Array.isArray(value)) {
        const ul = el('ul', 'ouinpo-projects-simple-list');
        value.forEach(function (item) {
          if (typeof item === 'object' && item) {
            ul.appendChild(el('li', '', aiTitleForItem(item, payload.kind) + ' - ' + aiDescriptionForItem(item)));
          } else {
            ul.appendChild(el('li', '', text(item)));
          }
        });
        section.appendChild(ul);
      } else {
        section.appendChild(el('p', '', text(value)));
      }
      preview.appendChild(section);
    });
  }

  function renderAiPayload(root, payload) {
    const status = root.querySelector('[data-ouinpo-projects-ai-status]');
    if (!payload || typeof payload !== 'object') {
      if (status) {
        status.textContent = 'Reponse IA inattendue.';
      }
      root._ouinpoProjectsAiPayload = null;
      return;
    }

    const applyable = {
      suggest_tasks: 'tasks',
      suggest_deliverables: 'deliverables',
      suggest_competencies: 'competency_links'
    };
    if (applyable[payload.kind]) {
      renderAiList(root, payload, applyable[payload.kind], true);
      return;
    }

    if (payload.kind === 'analyze_risks') {
      renderAiList(root, payload, 'risks', false);
      return;
    }

    renderAiSummary(root, payload);
  }

  function setAiBusy(root, busy) {
    root.classList.toggle('is-loading', busy);
    root.querySelectorAll('[data-ouinpo-projects-ai-action], [data-ouinpo-projects-ai-apply]').forEach(function (button) {
      button.disabled = busy;
    });
  }

  function clearAiPreview(root) {
    const preview = root.querySelector('[data-ouinpo-projects-ai-preview]');
    const apply = root.querySelector('[data-ouinpo-projects-ai-apply]');
    if (preview) {
      preview.innerHTML = '';
    }
    if (apply) {
      apply.hidden = true;
    }
    root._ouinpoProjectsAiPayload = null;
  }

  function bindAiAssistant(root) {
    const status = root.querySelector('[data-ouinpo-projects-ai-status]');
    const context = root.querySelector('[data-ouinpo-projects-ai-context]');
    const apply = root.querySelector('[data-ouinpo-projects-ai-apply]');

    root.addEventListener('click', function (event) {
      const action = event.target.closest('[data-ouinpo-projects-ai-action]');
      if (!action) {
        return;
      }

      clearAiPreview(root);
      setAiBusy(root, true);
      status.textContent = 'Generation en cours...';
      request('/projects/' + encodeURIComponent(root.dataset.projectId) + '/ai/' + encodeURIComponent(action.dataset.ouinpoProjectsAiAction), {
        method: 'POST',
        body: JSON.stringify({ teacher_context: context ? context.value : '' })
      }).then(function (payload) {
        status.textContent = text(payload.ai_notice || 'Proposition IA recue.');
        renderAiPayload(root, payload);
      }).catch(function (error) {
        status.textContent = error.message || 'Erreur IA.';
      }).then(function () {
        setAiBusy(root, false);
      });
    });

    if (apply) {
      apply.addEventListener('click', function () {
        const payload = root._ouinpoProjectsAiPayload;
        if (!payload || !payload.items || !payload.items.length) {
          return;
        }

        const checked = Array.from(root.querySelectorAll('.ouinpo-projects-ai-item input[type="checkbox"]:checked'));
        const items = checked.map(function (checkbox) {
          return payload.items[parseInt(checkbox.dataset.index, 10)];
        }).filter(Boolean);

        if (!items.length) {
          status.textContent = 'Aucune proposition selectionnee.';
          return;
        }

        if (!window.confirm('Appliquer les propositions selectionnees ?')) {
          return;
        }

        setAiBusy(root, true);
        status.textContent = 'Application en cours...';
        request('/projects/' + encodeURIComponent(root.dataset.projectId) + '/ai/apply-suggestion', {
          method: 'POST',
          body: JSON.stringify({ kind: payload.kind, items: items })
        }).then(function (result) {
          const applied = result.applied || {};
          const count = Object.keys(applied).reduce(function (total, key) {
            return total + (Array.isArray(applied[key]) ? applied[key].length : 0);
          }, 0);
          status.textContent = count + ' element(s) applique(s).';
          apply.hidden = true;
          root._ouinpoProjectsAiPayload = null;
        }).catch(function (error) {
          status.textContent = error.message || 'Application IA impossible.';
        }).then(function () {
          setAiBusy(root, false);
        });
      });
    }
  }

  function collectStudentAiInput(root) {
    const body = {};
    root.querySelectorAll('[data-ouinpo-projects-student-ai-field]').forEach(function (field) {
      body[field.dataset.ouinpoProjectsStudentAiField] = field.value || '';
    });
    return body;
  }

  function renderStringList(parent, title, items) {
    if (!Array.isArray(items) || !items.length) {
      return;
    }
    const section = el('section', 'ouinpo-projects-ai-summary-section');
    section.appendChild(el('h3', '', title));
    const list = el('ul', 'ouinpo-projects-simple-list');
    items.forEach(function (item) {
      list.appendChild(el('li', '', text(item)));
    });
    section.appendChild(list);
    parent.appendChild(section);
  }

  function renderStudentAiPayload(root, payload) {
    const preview = root.querySelector('[data-ouinpo-projects-student-ai-preview]');
    const copy = root.querySelector('[data-ouinpo-projects-student-ai-copy]');
    preview.innerHTML = '';
    root._ouinpoProjectsStudentAiText = '';

    if (!payload || typeof payload !== 'object') {
      preview.appendChild(el('p', 'ouinpo-projects-empty', 'Reponse IA inattendue.'));
      copy.hidden = true;
      return;
    }

    const warnings = Array.isArray(payload.warnings) ? payload.warnings : [];
    if (warnings.length) {
      const warningBox = el('div', 'ouinpo-projects-ai-warning');
      warnings.forEach(function (warning) {
        warningBox.appendChild(el('p', '', text(warning)));
      });
      preview.appendChild(warningBox);
    }

    const copyLines = [];
    if (payload.kind === 'reflection_questions' && Array.isArray(payload.questions)) {
      const list = el('div', 'ouinpo-projects-ai-items');
      payload.questions.forEach(function (item) {
        const card = el('article', 'ouinpo-projects-ai-item');
        card.appendChild(el('strong', '', text(item.theme || 'Question')));
        card.appendChild(el('span', '', text(item.question)));
        if (item.why_it_matters) {
          card.appendChild(el('small', '', text(item.why_it_matters)));
        }
        list.appendChild(card);
        copyLines.push('- ' + text(item.question));
      });
      preview.appendChild(list);
    } else if (payload.kind === 'personal_summary' && payload.personal_summary) {
      const summary = payload.personal_summary;
      const section = el('section', 'ouinpo-projects-ai-summary-section');
      section.appendChild(el('h3', '', 'Brouillon'));
      section.appendChild(el('p', '', text(summary.draft)));
      preview.appendChild(section);
      copyLines.push(text(summary.draft));
      renderStringList(preview, 'Forces a garder', summary.strengths_to_keep);
      renderStringList(preview, 'Points a clarifier', summary.points_to_clarify);
      renderStringList(preview, 'Traces a mentionner', summary.evidence_to_mention);
      renderStringList(preview, 'Questions avant soumission', summary.questions_before_submission);
    } else if (payload.kind === 'portfolio_draft' && payload.portfolio_draft) {
      const draft = payload.portfolio_draft;
      [
        ['Contexte', 'context'],
        ['Mon role', 'my_role'],
        ['Productions', 'productions'],
        ['Competences', 'skills'],
        ['Difficultes et solutions', 'difficulties_and_solutions'],
        ['Bilan personnel', 'personal_review']
      ].forEach(function (pair) {
        const value = text(draft[pair[1]]);
        if (!value) {
          return;
        }
        const section = el('section', 'ouinpo-projects-ai-summary-section');
        section.appendChild(el('h3', '', pair[0]));
        section.appendChild(el('p', '', value));
        preview.appendChild(section);
        copyLines.push(pair[0] + '\n' + value);
      });
      renderStringList(preview, 'A verifier', draft.to_verify);
    }

    if (!preview.childNodes.length) {
      preview.appendChild(el('p', 'ouinpo-projects-empty', 'Aucun brouillon exploitable.'));
      copy.hidden = true;
      return;
    }

    root._ouinpoProjectsStudentAiText = copyLines.join('\n\n');
    copy.hidden = !root._ouinpoProjectsStudentAiText;
  }

  function setStudentAiBusy(root, busy) {
    root.classList.toggle('is-loading', busy);
    root.querySelectorAll('[data-ouinpo-projects-student-ai-action], [data-ouinpo-projects-student-ai-copy]').forEach(function (button) {
      button.disabled = busy;
    });
  }

  function bindStudentAi(root) {
    const status = root.querySelector('[data-ouinpo-projects-student-ai-status]');
    const preview = root.querySelector('[data-ouinpo-projects-student-ai-preview]');
    const copy = root.querySelector('[data-ouinpo-projects-student-ai-copy]');

    root.addEventListener('click', function (event) {
      const action = event.target.closest('[data-ouinpo-projects-student-ai-action]');
      if (!action) {
        return;
      }

      if (preview) {
        preview.innerHTML = '';
      }
      if (copy) {
        copy.hidden = true;
      }
      root._ouinpoProjectsStudentAiText = '';
      setStudentAiBusy(root, true);
      if (status) {
        status.textContent = 'Generation en cours...';
      }
      request('/projects/' + encodeURIComponent(root.dataset.projectId) + '/student-ai/' + encodeURIComponent(action.dataset.ouinpoProjectsStudentAiAction), {
        method: 'POST',
        body: JSON.stringify(collectStudentAiInput(root))
      }).then(function (payload) {
        if (status) {
          status.textContent = text(payload && payload.ai_notice ? payload.ai_notice : 'Brouillon IA recu.');
        }
        renderStudentAiPayload(root, payload);
      }).catch(function (error) {
        if (status) {
          status.textContent = error.message || 'Erreur IA.';
        }
      }).then(function () {
        setStudentAiBusy(root, false);
      });
    });

    if (copy) {
      copy.addEventListener('click', function () {
        const value = root._ouinpoProjectsStudentAiText || '';
        if (!value) {
          return;
        }
        copyText(value).then(function () {
          if (status) {
            status.textContent = 'Brouillon copie.';
          }
        }).catch(function () {
          if (status) {
            status.textContent = 'Copie impossible. Le brouillon reste affiche.';
          }
        });
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-ouinpo-projects-board]').forEach(bindBoard);
    document.querySelectorAll('[data-ouinpo-projects-journal]').forEach(bindJournal);
    document.querySelectorAll('[data-ouinpo-projects-deliverables]').forEach(bindDeliverables);
    document.querySelectorAll('[data-ouinpo-projects-evidence]').forEach(bindEvidence);
    document.querySelectorAll('[data-ouinpo-projects-export]').forEach(bindExports);
    document.querySelectorAll('[data-ouinpo-projects-ai]').forEach(bindAiAssistant);
    document.querySelectorAll('[data-ouinpo-projects-student-ai]').forEach(bindStudentAi);
    document.querySelectorAll('[data-ouinpo-projects-print]').forEach(function (button) {
      button.addEventListener('click', function () {
        window.print();
      });
    });
  });
}());
