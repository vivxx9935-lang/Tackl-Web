class CategoryManager {
  constructor(listElement, formElement, inputElement, selectElement = null) {
    this.categories = ['School Work', 'Housework', 'Actual Work', 'Social', 'Event'];
    this.listElement = listElement;
    this.formElement = formElement;
    this.inputElement = inputElement;
    this.selectElement = selectElement;
    this.render();
    this.bindEvents();
  }

  bindEvents() {
    if (!this.formElement) return;
    this.formElement.addEventListener('submit', event => {
      event.preventDefault();
      const newCategory = this.inputElement.value.trim();
      if (newCategory) {
        this.addCategory(newCategory);
        this.inputElement.value = '';
      }
    });
  }

  addCategory(category) {
    const normalized = category.charAt(0).toUpperCase() + category.slice(1).toLowerCase();
    if (!this.categories.includes(normalized)) {
      this.categories.push(normalized);
      this.render();
      this.showMessage(`Category "${normalized}" added.`);
    }
  }

  render() {
    if (this.listElement) {
      this.listElement.innerHTML = this.categories
        .map(name => `<span class="chip">${name}</span>`)
        .join('');
    }
    this.updateCategorySelect();
  }

  updateCategorySelect() {
    if (!this.selectElement) return;
    this.selectElement.innerHTML = this.categories
      .map(name => `<option value="${name}">${name}</option>`)
      .join('');
  }

  showMessage(message) {
    const flash = document.createElement('div');
    flash.className = 'flash-message';
    flash.textContent = message;
    document.body.appendChild(flash);
    setTimeout(() => flash.classList.add('visible'), 10);
    setTimeout(() => flash.classList.remove('visible'), 3200);
    setTimeout(() => flash.remove(), 3800);
  }
}

class TaskManager {
  constructor(formElement, listElement, gaugeFill, gaugeText, categoryManager = null) {
    this.storageKey = 'tackl_tasks';
    this.tasks = this.loadTasks();
    this.formElement = formElement;
    this.listElement = listElement;
    this.gaugeFill = gaugeFill;
    this.gaugeText = gaugeText;
    this.categoryManager = categoryManager;
    this.bindEvents();
    this.renderTasks();
  }

  bindEvents() {
    if (!this.formElement) return;
    this.formElement.addEventListener('submit', event => {
      event.preventDefault();
      const title = this.formElement.querySelector('#task-title').value.trim();
      const category = this.formElement.querySelector('#task-category').value;
      const time = this.formElement.querySelector('#task-time').value;
      const date = this.formElement.querySelector('#task-date') ? this.formElement.querySelector('#task-date').value : '';

      if (!title) {
        this.showMessage('Please enter a task title.');
        return;
      }

      this.addTask({ title, category, time, date });
      this.formElement.reset();
      if (this.categoryManager) {
        this.categoryManager.updateCategorySelect();
      }
    });
  }

  loadTasks() {
    try {
      const serialized = localStorage.getItem(this.storageKey);
      return serialized ? JSON.parse(serialized) : [];
    } catch (error) {
      return [];
    }
  }

  saveTasks() {
    try {
      localStorage.setItem(this.storageKey, JSON.stringify(this.tasks));
    } catch (error) {
      console.warn('Unable to save tasks to localStorage.');
    }
  }

  addTask(task) {
    this.tasks.unshift(task);
    this.saveTasks();
    this.renderTasks();
    this.showMessage(`Task "${task.title}" added.`);
  }

  renderTasks() {
    if (!this.listElement) return;
    if (this.tasks.length === 0) {
      this.listElement.innerHTML = '<p class="hint-text">No tasks added yet. Use the form above to build your day.</p>';
      this.updateWorkloadGauge();
      return;
    }

    this.listElement.innerHTML = this.tasks
      .map(task => `
        <div class="task-item">
          <strong>${this.escapeHtml(task.title)}</strong>
          <small>${this.escapeHtml(task.category)}${task.date ? ' • ' + this.escapeHtml(task.date) : ''}${task.time ? ' • ' + this.escapeHtml(task.time) : ''}</small>
        </div>
      `)
      .join('');

    this.updateWorkloadGauge();
  }

  updateWorkloadGauge() {
    if (!this.gaugeFill || !this.gaugeText) return;
    const count = this.tasks.length;
    const percent = Math.min(100, count * 16);
    this.gaugeFill.style.width = `${percent}%`;

    if (count === 0) {
      this.gaugeText.textContent = 'No tasks yet.';
    } else if (count <= 2) {
      this.gaugeText.textContent = `${count} task${count === 1 ? '' : 's'} — easy day ahead.`;
    } else if (count <= 5) {
      this.gaugeText.textContent = `${count} tasks — balanced workload.`;
    } else {
      this.gaugeText.textContent = `${count} tasks — busy schedule.`;
    }
  }

  escapeHtml(value) {
    return value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  showMessage(message) {
    const flash = document.createElement('div');
    flash.className = 'flash-message';
    flash.textContent = message;
    document.body.appendChild(flash);
    setTimeout(() => flash.classList.add('visible'), 10);
    setTimeout(() => flash.classList.remove('visible'), 3200);
    setTimeout(() => flash.remove(), 3800);
  }
}

class SchedulePage {
  constructor() {
    this.storageKey = 'tackl_tasks';
    this.todayList = document.querySelector('#today-tasks-list');
    this.weekList = document.querySelector('#week-tasks-list');
    this.weekDaySlots = document.querySelectorAll('.week-day-tasks');
    this.renderSchedule();
  }

  loadTasks() {
    try {
      const serialized = localStorage.getItem(this.storageKey);
      return serialized ? JSON.parse(serialized) : [];
    } catch (error) {
      return [];
    }
  }

  renderSchedule() {
    if (!this.todayList && !this.weekList && this.weekDaySlots.length === 0) return;

    const tasks = this.loadTasks();
    const today = new Date().toISOString().slice(0, 10);
    const currentWeek = this.getCurrentWeekDays();

    const todaysTasks = tasks.filter(task => task.date === today);
    const weeklyTasks = tasks.filter(task => currentWeek.includes(task.date));

    if (this.todayList) {
      this.todayList.innerHTML = todaysTasks.length > 0
        ? todaysTasks.map(task => this.renderTaskItem(task)).join('')
        : '<p class="hint-text">No items for today yet.</p>';
    }

    if (this.weekList) {
      this.weekList.innerHTML = weeklyTasks.length > 0
        ? weeklyTasks.map(task => this.renderTaskItem(task)).join('')
        : '<p class="hint-text">No tasks scheduled for this week yet.</p>';
    }

    this.weekDaySlots.forEach(slot => {
      const slotDate = slot.dataset.day;
      const dayTasks = tasks.filter(task => task.date === slotDate);
      slot.innerHTML = dayTasks.length > 0
        ? dayTasks.map(task => `<div class="mini-task">${this.escapeHtml(task.title)}${task.time ? ' • ' + this.escapeHtml(task.time) : ''}</div>`).join('')
        : '<span class="hint-text">No tasks</span>';
    });
  }

  getCurrentWeekDays() {
    const now = new Date();
    const dayOfWeek = now.getDay();
    const sunday = new Date(now);
    sunday.setDate(now.getDate() - dayOfWeek);
    return Array.from({ length: 7 }, (_, index) => {
      const date = new Date(sunday);
      date.setDate(sunday.getDate() + index);
      return date.toISOString().slice(0, 10);
    });
  }

  renderTaskItem(task) {
    return `
      <div class="task-item">
        <strong>${this.escapeHtml(task.title)}</strong>
        <small>${this.escapeHtml(task.category)}${task.time ? ' • ' + this.escapeHtml(task.time) : ''}${task.date ? ' • ' + this.escapeHtml(task.date) : ''}</small>
      </div>
    `;
  }

  escapeHtml(value) {
    return value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
}

class PopupManager {
  static show(message) {
    const popup = document.querySelector('.popup[data-popup-message]');
    if (!popup) return;
    popup.textContent = message;
    popup.classList.add('visible');
    setTimeout(() => popup.classList.remove('visible'), 2400);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const categoryList = document.querySelector('#category-list');
  const categoryForm = document.querySelector('#new-category-form');
  const categoryInput = document.querySelector('#new-category-input');
  const categorySelect = document.querySelector('#task-category');
  const taskForm = document.querySelector('#new-task-form');
  const taskList = document.querySelector('#task-list');
  const workloadFill = document.querySelector('#workload-fill');
  const workloadText = document.querySelector('#workload-text');

  let categoryManager = null;
  if (categoryList && categoryForm && categoryInput) {
    categoryManager = new CategoryManager(categoryList, categoryForm, categoryInput, categorySelect);
  }

  if (taskForm && taskList && taskForm.dataset.clientTasks === 'true') {
    new TaskManager(taskForm, taskList, workloadFill, workloadText, categoryManager);
  }

  if (document.body.dataset.clientSchedule === 'true') {
    new SchedulePage();
  }

  const customCategorySelect = document.querySelector('#task-category');
  const customCategoryGroup = document.querySelector('#custom-category-group');
  if (customCategorySelect && customCategoryGroup) {
    const toggleCustomCategory = () => {
      const showCustom = customCategorySelect.value === '_custom_';
      customCategoryGroup.style.display = showCustom ? 'block' : 'none';
      if (!showCustom) {
        const customInput = customCategoryGroup.querySelector('input[name="custom_category"]');
        if (customInput) customInput.value = '';
      }
    };
    customCategorySelect.addEventListener('change', toggleCustomCategory);
    toggleCustomCategory();
  }

  const passwordFieldGroups = document.querySelectorAll('.password-field');
  passwordFieldGroups.forEach(group => {
    const passwordInput = group.querySelector('input[type="password"]');
    const toggleButton = group.querySelector('.show-password-button');
    if (!passwordInput || !toggleButton) return;

    toggleButton.addEventListener('click', () => {
      const isHidden = passwordInput.type === 'password';
      passwordInput.type = isHidden ? 'text' : 'password';
      toggleButton.textContent = isHidden ? 'Hide' : 'Show';
      toggleButton.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
      toggleButton.classList.toggle('password-visible', isHidden);
    });
  });

  const popup = document.querySelector('.popup[data-popup-message]');
  if (popup) {
    const message = popup.dataset.popupMessage;
    if (message) {
      PopupManager.show(message);
    }
  }
});
