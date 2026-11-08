// Custom todo JS avec navigation par onglets (filtres)

const taskList = document.querySelector("#task-list");
const newTaskInput = document.querySelector("#new-task-input");
const addTaskButton = document.querySelector("#add-task-button");
const filterItems = document.querySelectorAll("[data-filter]");

const STATUS = {
  IN_PROGRESS: "en_cours",
  PENDING: "en_attente",
};

const app = {
  tasks: [],
  taskList,
  newTaskInput,
  currentFilter: "all", // all | today | completed | in_progress | pending
};

let idCounter = 0;

function todayISO() {
  return new Date().toLocaleDateString("en-CA"); // format YYYY-MM-DD
}

function createTask(title, isCompleted = false, status = STATUS.IN_PROGRESS) {
  return {
    id: Date.now() + idCounter++,
    title,
    isCompleted,
    status,
    date: new Date().toLocaleDateString(),
    dateISO: todayISO(),
  };
}

/* ---------- Filtres ---------- */

function matchesFilter(task, filter) {
  switch (filter) {
    case "today":
      return task.dateISO === todayISO();
    case "completed":
      return task.isCompleted;
    case "in_progress":
      return !task.isCompleted && task.status === STATUS.IN_PROGRESS;
    case "pending":
      return !task.isCompleted && task.status === STATUS.PENDING;
    case "all":
    default:
      return true;
  }
}

function updateCounts() {
  document.querySelectorAll("[data-count]").forEach((badge) => {
    const filter = badge.dataset.count;
    badge.textContent = app.tasks.filter((t) => matchesFilter(t, filter)).length;
  });
}

function updateActiveTab() {
  filterItems.forEach((el) => {
    const li = el.closest("li");
    if (li) li.classList.toggle("active", el.dataset.filter === app.currentFilter);
  });
}

function renderTasks() {
  if (!app.taskList) return;
  app.taskList.innerHTML = "";

  const visibleTasks = app.tasks.filter((t) => matchesFilter(t, app.currentFilter));

  if (visibleTasks.length === 0) {
    const empty = document.createElement("li");
    empty.className = "text-center c-o-light";
    empty.textContent = "Aucune tâche dans cette catégorie.";
    app.taskList.appendChild(empty);
  } else {
    visibleTasks.forEach((task) => addTaskToList(task, app.taskList));
  }

  updateCounts();
  updateActiveTab();
}

function setFilter(filter) {
  app.currentFilter = filter;
  renderTasks();
}

/* ---------- Tâches ---------- */

function addTaskToList(task, taskList) {
  if (!taskList) {
    console.error("Task list element not found.");
    return;
  }
  taskList.appendChild(createTaskElement(task));
}

function addTask(app) {
  if (!app.newTaskInput) return;
  const newTaskTitle = app.newTaskInput.value;
  if (!newTaskTitle.trim()) return; // Évite les tâches vides
  const newTask = createTask(newTaskTitle.trim());
  app.tasks.push(newTask);

  saveTasksToLocalStorage(app.tasks);
  app.newTaskInput.value = "";
  renderTasks();
}

function createTaskElement(task) {
  const taskElement = document.createElement("li");

  const taskCheckBox = document.createElement("input");
  taskCheckBox.type = "checkbox";
  taskCheckBox.classList.add("form-check-input", "checkbox-primary");
  taskCheckBox.checked = task.isCompleted;

  const taskText = document.createElement("span");
  taskText.textContent = task.title;
  taskText.classList.toggle("completed", task.isCompleted);

  taskCheckBox.addEventListener("change", () => {
    task.isCompleted = taskCheckBox.checked;
    saveTasksToLocalStorage(app.tasks);
    renderTasks(); // la tâche change d'onglet si besoin
  });

  const actionContainer = document.createElement("div");
  actionContainer.classList.add("task-date");

  // Sélecteur de statut (en cours / en attente)
  const statusSelect = document.createElement("select");
  statusSelect.className = "form-select form-select-sm w-auto me-2";
  statusSelect.disabled = task.isCompleted;
  [
    [STATUS.IN_PROGRESS, "En cours"],
    [STATUS.PENDING, "En attente"],
  ].forEach(([value, label]) => {
    const opt = document.createElement("option");
    opt.value = value;
    opt.textContent = label;
    opt.selected = task.status === value;
    statusSelect.appendChild(opt);
  });
  statusSelect.addEventListener("change", () => {
    task.status = statusSelect.value;
    saveTasksToLocalStorage(app.tasks);
    renderTasks();
  });
  actionContainer.appendChild(statusSelect);

  const dateSpan = document.createElement("span");
  dateSpan.classList.add("c-o-light");
  dateSpan.textContent = `${task.date}`;
  actionContainer.appendChild(dateSpan);

  const taskDeleteButton = document.createElement("button");
  taskDeleteButton.className = "delete-button";
  const trashIcon = document.createElement("i");
  trashIcon.className = "fa-solid fa-trash"; // icône FontAwesome
  taskDeleteButton.appendChild(trashIcon);

  taskDeleteButton.addEventListener("click", () => {
    app.tasks = app.tasks.filter((t) => t.id !== task.id);
    saveTasksToLocalStorage(app.tasks);
    renderTasks();
  });

  actionContainer.appendChild(taskDeleteButton);

  taskElement.appendChild(taskCheckBox);
  taskElement.appendChild(taskText);
  taskElement.appendChild(actionContainer);

  return taskElement;
}

/* ---------- Stockage ---------- */

function saveTasksToLocalStorage(tasks) {
  try {
    localStorage.setItem("tasks", JSON.stringify(tasks));
  } catch (e) {
    console.error("Impossible de sauvegarder les tâches", e);
  }
}

function loadTasksFromLocalStorage() {
  try {
    const stored = JSON.parse(localStorage.getItem("tasks"));
    if (Array.isArray(stored) && stored.length) {
      // Compatibilité avec d'anciennes tâches sans statut / dateISO
      return stored.map((t) => ({
        status: STATUS.IN_PROGRESS,
        dateISO: todayISO(),
        ...t,
      }));
    }
  } catch (e) {
    /* ignore */
  }
  return null;
}

function getDefaultTasks() {
  return [
    createTask("Update all admin themes", false, STATUS.IN_PROGRESS),
    createTask("Prepare a summary presentation for the stakeholders and finish the in-depth analysis for the quarterly financial report.", false, STATUS.IN_PROGRESS),
    createTask("Review project documentation", true),
    createTask("Plan out and complete the weekend's events, including any planned excursions, get-togethers, and downtime.", false, STATUS.PENDING),
  ];
}

/* ---------- Initialisation ---------- */

function init() {
  app.tasks = loadTasksFromLocalStorage() || getDefaultTasks();
  saveTasksToLocalStorage(app.tasks);

  // Navigation par onglets
  filterItems.forEach((el) => {
    el.addEventListener("click", (event) => {
      event.preventDefault();
      setFilter(el.dataset.filter);
    });
  });

  // Ajout de tâche (uniquement si les éléments existent dans la page)
  if (addTaskButton) {
    addTaskButton.addEventListener("click", () => addTask(app));
  }
  if (newTaskInput) {
    newTaskInput.addEventListener("keydown", (event) => {
      if (event.key === "Enter") addTask(app);
    });
  }

  renderTasks();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
