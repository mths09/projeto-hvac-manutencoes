const API = "../../../backend/solicitacoes_admin.php";
const STORAGE_KEY = "hvac-agenda-v1";
const STATUS_LABELS = {
  SOLICITADO: "Solicitado",
  AGENDADO: "Agendado",
  EM_ANDAMENTO: "Em Andamento",
  CONCLUIDO: "Concluído",
  CANCELADO: "Cancelado",
};
const statuses = Object.values(STATUS_LABELS);
const TODAY = localDate();
let orders = [];
let technicians = [];
let page = "overview";
let search = "";
let filter = "Todos";
let adminCsrf = "";
let adminLoading = true;
let adminError = "";
let adminMessage = "";
let adminMessageError = false;

const content = document.querySelector("#content");
const dialog = document.querySelector("#order-dialog");
const form = document.querySelector("#order-form");

function localDate() {
  const now = new Date();
  return [
    now.getFullYear(),
    String(now.getMonth() + 1).padStart(2, "0"),
    String(now.getDate()).padStart(2, "0"),
  ].join("-");
}

function normalizarPedido(pedido) {
  const inicio = String(pedido.inicio_previsto || "");
  const criado = String(pedido.criada_em || "");
  const codigoStatus = String(pedido.status_codigo || "SOLICITADO");
  return {
    id: String(pedido.protocolo),
    client: String(pedido.cliente || "Cliente"),
    phone: String(pedido.telefone || ""),
    equipment: String(pedido.servico || "Serviço"),
    technician: String(pedido.tecnico || ""),
    technicianId: String(pedido.tecnico_id || ""),
    date: inicio ? inicio.slice(0, 10) : criado.slice(0, 10),
    time: inicio ? inicio.slice(11, 16) : "—",
    status: STATUS_LABELS[codigoStatus] || String(pedido.status_nome || codigoStatus),
    statusCode: codigoStatus,
    statusOptions: Array.isArray(pedido.status_permitidos)
      ? pedido.status_permitidos.filter((codigo) => STATUS_LABELS[codigo])
      : [],
    urgent: Boolean(pedido.urgente),
    createdAt: criado.slice(0, 10),
    description: String(pedido.descricao || ""),
    address: String(pedido.endereco || ""),
    totalDocuments: Number(pedido.total_documentos) || 0,
    attachments: [],
  };
}

async function carregarSolicitacoesAdmin(mostrarCarregamento = true) {
  if (mostrarCarregamento) {
    adminLoading = true;
    adminError = "";
    render();
  }
  try {
    const resposta = await fetch(API, {
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" },
    });
    const dados = await resposta.json();
    if (resposta.status === 401 || resposta.status === 403) {
      window.location.replace("./login.html");
      return;
    }
    if (!resposta.ok || dados?.sucesso !== true || !Array.isArray(dados.pedidos) || !Array.isArray(dados.tecnicos)) {
      throw new Error(dados?.mensagem || "Não foi possível carregar as solicitações.");
    }
    if (typeof dados.csrf !== "string") {
      throw new Error("O servidor não forneceu a proteção de sessão do painel.");
    }
    orders = dados.pedidos.map(normalizarPedido);
    technicians = dados.tecnicos.map((tecnico) => ({
      id: String(tecnico.id),
      name: String(tecnico.nome),
      initials: iniciais(tecnico.nome),
      specialty: "Equipe técnica",
    }));
    adminCsrf = dados.csrf;
    adminError = "";
    adminLoading = false;
    if (mostrarCarregamento) render();
  } catch (erro) {
    adminLoading = false;
    adminError = "Não foi possível carregar as solicitações do banco. Verifique o servidor e tente novamente.";
    console.error(erro);
    render();
  }
}

async function enviarAcaoAdmin(pedido, acao, valores, controle) {
  if (!adminCsrf) return;
  const valorAnterior = controle.value;
  controle.disabled = true;
  adminMessage = "";
  adminMessageError = false;
  try {
    const resposta = await fetch(API, {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify({ csrf: adminCsrf, protocolo: pedido.id, acao, ...valores }),
    });
    const dados = await resposta.json();
    if (resposta.status === 401 || resposta.status === 403) {
      window.location.replace("./login.html");
      return;
    }
    if (!resposta.ok || dados?.sucesso !== true) {
      throw new Error(dados?.mensagem || "Não foi possível atualizar o pedido.");
    }
    adminMessage = dados.mensagem || "Alteração salva.";
    await carregarSolicitacoesAdmin(false);
    if (adminError) adminMessageError = true;
  } catch (erro) {
    controle.disabled = false;
    controle.value = valorAnterior;
    adminMessage = erro.message || "Não foi possível atualizar o pedido.";
    adminMessageError = true;
    console.error(erro);
  }
  render();
}

function iniciais(nome) {
  const partes = String(nome || "").trim().split(/\s+/).filter(Boolean);
  if (!partes.length) return "?";
  return `${partes[0][0]}${partes.length > 1 ? partes.at(-1)[0] : ""}`.toUpperCase();
}

function escapeHTML(value) {
  return String(value).replace(
    /[&<>"']/g,
    (character) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      })[character],
  );
}

function dateBR(date) {
  return date.split("-").reverse().join("/");
}

function sorted(list) {
  return [...list].sort((a, b) =>
    (a.date + a.time).localeCompare(b.date + b.time),
  );
}

function badge(status) {
  const className = {
    Solicitado: "requested",
    Agendado: "scheduled",
    "Em Andamento": "progress",
    Concluído: "done",
    Cancelado: "cancelled",
  }[status];
  return `<span class="badge ${className}">${escapeHTML(status)}</span>`;
}

function urgentRow(order) {
  return `
        <div class="row">
          <span class="id">${escapeHTML(order.id)}</span>
          <strong>${escapeHTML(order.client)}</strong>
          <span class="muted">${escapeHTML(order.equipment)}</span>
          <div class="right">
            ${badge(order.status)}
            <span class="date">${dateBR(order.date)} ${escapeHTML(order.time)}</span>
          </div>
        </div>`;
}

function scheduleRow(order) {
  return `
        <div class="row">
          <span class="time">${escapeHTML(order.time)}</span>
          <strong>${escapeHTML(order.client)}</strong>
          <span class="muted">· ${escapeHTML(order.equipment)}</span>
          <div class="right">
            <span class="muted">${escapeHTML(order.technician || "Não atribuído")}</span>
            ${badge(order.status)}
          </div>
        </div>`;
}

function renderOverview() {
  const todayOrders = sorted(orders.filter((order) => order.date === TODAY));
  const urgentOrders = sorted(
    orders.filter((order) => order.urgent && !["CONCLUIDO", "CANCELADO"].includes(order.statusCode)),
  );
  const pending = orders.filter((order) => order.statusCode === "SOLICITADO").length;
  const ongoing = orders.filter(
    (order) => order.status === "Em Andamento",
  ).length;
  const completed = orders.filter(
    (order) =>
      order.status === "Concluído" && order.date.startsWith(TODAY.slice(0, 7)),
  ).length;

  content.innerHTML = `
        <section class="stats" aria-label="Resumo dos atendimentos">
          ${[
            ["HOJE", todayOrders.length, "atendimentos agendados", "cyan"],
            ["PENDENTES", pending, "aguardando ação", "orange"],
            ["EM ANDAMENTO", ongoing, "em campo agora", "purple"],
            ["CONCLUÍDOS", completed, "este mês", "green"],
          ]
            .map(
              ([label, value, description, color]) => `
            <div class="stat">
              <div class="stat-label">${label}</div>
              <div class="stat-value ${color}">${value}</div>
              <small>${description}</small>
            </div>
          `,
            )
            .join("")}
        </section>

        <section class="panel urgent">
          <h2>⚡ Pedidos Urgentes (${urgentOrders.length})</h2>
          ${
            urgentOrders.map(urgentRow).join("") ||
            '<div class="empty">Nenhum pedido urgente.</div>'
          }
        </section>

        <section class="panel">
          <h2>Agenda de Hoje — ${dateBR(TODAY)}</h2>
          ${
            todayOrders.map(scheduleRow).join("") ||
            '<div class="empty">Nenhum atendimento agendado.</div>'
          }
        </section>

        <section class="technicians" aria-label="Equipe técnica">
          ${technicians
            .map((technician) => {
              const active = orders.filter(
                (order) =>
                  order.technician === technician.name &&
                  order.status !== "Concluído",
              ).length;
              return `
              <div class="technician">
                <div class="avatar">${technician.initials}</div>
                <div>
                  <strong>${technician.name}</strong>
                  <small>${technician.specialty}</small>
                  <small class="active-jobs">${active} atendimentos ativos</small>
                </div>
              </div>`;
            })
            .join("")}
        </section>`;
}

function table(list, editable) {
  if (!list.length) return '<div class="empty">Nenhum pedido encontrado.</div>';

  return `
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Pedido</th><th>Cliente</th><th>Equipamento</th>
                <th>Técnico</th><th>Agendamento</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              ${sorted(list)
                .map(
                  (order) => `
                <tr>
                  <td class="${order.urgent ? "id" : "cyan"}">
                    ${escapeHTML(order.id)}${order.urgent ? " ⚡" : ""}
                  </td>
                  <td>${escapeHTML(order.client)}</td>
                  <td>${escapeHTML(order.equipment)}</td>
                  <td>${escapeHTML(order.technician)}</td>
                  <td>${dateBR(order.date)} ${escapeHTML(order.time)}</td>
                  <td>${
                    editable
                      ? `
                    <select data-order-id="${escapeHTML(order.id)}"
                      aria-label="Status do pedido ${escapeHTML(order.id)}">
                      ${statuses
                        .map(
                          (status) => `
                        <option ${order.status === status ? "selected" : ""}>
                          ${status}
                        </option>
                      `,
                        )
                        .join("")}
                    </select>`
                      : badge(order.status)
                  }
                  </td>
                </tr>
              `,
                )
                .join("")}
            </tbody>
          </table>
        </div>`;
}

function filteredOrders(history) {
  return orders.filter((order) => {
    const matchesPage = history
      ? order.status === "Concluído"
      : order.status !== "Concluído";
    const matchesSearch = [
      order.id,
      order.client,
      order.equipment,
      order.technician,
    ]
      .join(" ")
      .toLocaleLowerCase("pt-BR")
      .includes(search.toLocaleLowerCase("pt-BR"));
    return (
      matchesPage &&
      matchesSearch &&
      (filter === "Todos" || order.status === filter)
    );
  });
}

function renderRequests(history = false) {
  // Mantém a aba Histórico em tabela.
  if (history) {
    content.innerHTML = `
      <div class="toolbar">
        <h1>Histórico</h1>

        <input
          id="history-search"
          placeholder="Buscar cliente, pedido ou técnico..."
          aria-label="Buscar no histórico"
          value="${escapeHTML(search)}"
        >
      </div>

      <section class="panel" id="history-results">
        ${table(filteredOrders(true), false)}
      </section>
    `;

    content
      .querySelector("#history-search")
      .addEventListener("input", (event) => {
        search = event.target.value;
        content.querySelector("#history-results").innerHTML = table(
          filteredOrders(true),
          false,
        );
      });

    return;
  }

  const filters = [
    ["Todos", "Todos"],
    ["Solicitado", "Solicitados"],
    ["Agendado", "Agendados"],
    ["Em Andamento", "Em Andamento"],
    ["Cancelado", "Cancelados"],
  ];

  const activeOrders = orders.filter((order) => order.statusCode !== "CONCLUIDO");

  if (!filters.some(([value]) => value === filter)) {
    filter = "Todos";
  }

  function statusBadge(status) {
    const classes = {
      Solicitado: "sr-requested",
      Agendado: "sr-scheduled",
      "Em Andamento": "sr-progress",
      Concluído: "sr-done",
      Cancelado: "sr-cancelled",
    };

    return `
      <span class="sr-badge ${classes[status] || "sr-requested"}">
        ${escapeHTML(status)}
      </span>
    `;
  }

  function renderCard(order) {
    const currentTechnician = order.technician || "Não atribuído";

    const phone = order.phone || "";
    const documentCount = Number(order.totalDocuments) || 0;
    const statusOptions = [...new Set([order.statusCode, ...order.statusOptions])];

    return `
      <article class="sr-card ${order.urgent ? "urgent-card" : ""}">
        <header class="sr-header">
          <div class="sr-tags">
            <strong class="sr-id">${escapeHTML(order.id)}</strong>

            ${
              order.urgent
                ? `
              <span class="sr-badge sr-urgent">⚡ Urgente</span>
            `
                : ""
            }

            ${statusBadge(order.status)}
          </div>

          <span class="sr-created">
            Criado:
            ${order.createdAt ? escapeHTML(dateBR(order.createdAt)) : "—"}
          </span>
        </header>

        <div class="sr-columns">
          <div>
            <div class="sr-label">Cliente</div>
            <div class="sr-value">${escapeHTML(order.client)}</div>

            ${
              phone
                ? `
              <a
                class="sr-phone"
                href="tel:${escapeHTML(phone.replace(/[^\d+]/g, ""))}"
              >${escapeHTML(phone)}</a>
            `
                : `
              <span class="sr-description">Telefone não informado</span>
            `
            }
          </div>

          <div>
            <div class="sr-label">Serviço</div>
            <div class="sr-value">${escapeHTML(order.equipment)}</div>
            <div class="sr-description">
              ${escapeHTML(order.description || "Sem descrição")}
            </div>
          </div>

          <div>
            <div class="sr-label">Agendado</div>
            <div class="sr-value">${escapeHTML(dateBR(order.date))}</div>
            <div class="sr-description">
              ${escapeHTML(order.time)} —
              ${escapeHTML(order.address || "Endereço não informado")}
            </div>
          </div>
        </div>

        <footer class="sr-footer">
          <label class="sr-field">
            Status:

            <select
              data-sr-status="${escapeHTML(order.id)}"
              aria-label="Status de ${escapeHTML(order.id)}"
            >
              ${statusOptions
                .map((codigo) => `
                <option
                  value="${escapeHTML(codigo)}"
                  ${order.statusCode === codigo ? "selected" : ""}
                >${escapeHTML(STATUS_LABELS[codigo])}</option>
              `)
                .join("")}
            </select>
          </label>

          <label class="sr-field">
            Técnico:

            <select
              data-sr-technician="${escapeHTML(order.id)}"
              aria-label="Técnico de ${escapeHTML(order.id)}"
            >
              <option value="" ${order.technicianId === "" ? "selected" : ""}>Não atribuído</option>
              ${technicians
                .map((tecnico) => `
                <option
                  value="${escapeHTML(tecnico.id)}"
                  ${tecnico.id === order.technicianId ? "selected" : ""}
                >${escapeHTML(tecnico.name)}</option>
              `)
                .join("")}
            </select>
          </label>

          <div class="sr-actions">
            ${
              documentCount
                ? `
              <span class="sr-files">
                📎 ${documentCount}
                ${documentCount === 1 ? "documento" : "documentos"}
              </span>
            `
                : ""
            }

            <button
              class="sr-details"
              data-sr-details="${escapeHTML(order.id)}"
            >Ver detalhes →</button>

            ${
              order.statusOptions.includes("CANCELADO")
                ? `<button class="sr-delete" data-sr-delete="${escapeHTML(order.id)}">Cancelar</button>`
                : ""
            }
          </div>
        </footer>
      </article>
    `;
  }

  content.innerHTML = `
    <section aria-label="Solicitações">
      <div class="sr-toolbar">
        <div class="sr-filters">
          ${filters
            .map(([value, label]) => {
              const count = activeOrders.filter(
                (order) => value === "Todos" || order.status === value,
              ).length;

              return `
              <button
                class="sr-filter ${filter === value ? "active" : ""}"
                data-sr-filter="${value}"
                aria-pressed="${filter === value}"
              >${label} (${count})</button>
            `;
            })
            .join("")}
        </div>

        <div class="sr-search">
          <input
            id="sr-search"
            placeholder="Buscar cliente, ID..."
            aria-label="Buscar solicitações"
            value="${escapeHTML(search)}"
          >

          <button class="sr-add" id="sr-add">+ Adicionar</button>
        </div>
      </div>

      <div class="sr-list" id="sr-list"></div>
    </section>
  `;

  const list = content.querySelector("#sr-list");

  function updateList() {
    const query = search.trim().toLocaleLowerCase("pt-BR");

    const visible = activeOrders.filter((order) => {
      const matchesStatus = filter === "Todos" || order.status === filter;

      const matchesSearch = [
        order.id,
        order.client,
        order.phone || "",
        order.equipment,
        order.technician || "",
      ]
        .join(" ")
        .toLocaleLowerCase("pt-BR")
        .includes(query);

      return matchesStatus && matchesSearch;
    });

    list.innerHTML =
      visible.map(renderCard).join("") ||
      `
      <div class="sr-empty">Nenhuma solicitação encontrada.</div>
    `;
  }

  content.querySelectorAll("[data-sr-filter]").forEach((button) => {
    button.addEventListener("click", () => {
      filter = button.dataset.srFilter;
      render();
    });
  });

  content.querySelector("#sr-search").addEventListener("input", (event) => {
    search = event.target.value;
    updateList();
  });

  content.querySelector("#sr-add").addEventListener("click", () => {
    document.querySelector("#new-order").click();
  });

  list.addEventListener("change", (event) => {
    const statusId = event.target.dataset.srStatus;
    const technicianId = event.target.dataset.srTechnician;
    const id = statusId || technicianId;

    if (!id) return;

    const order = orders.find((item) => item.id === id);
    if (!order) return;

    if (statusId) {
      if (!order.statusOptions.includes(event.target.value)) return;
      void enviarAcaoAdmin(order, "status", { status: event.target.value }, event.target);
    } else {
      void enviarAcaoAdmin(order, "tecnico", { tecnico_id: event.target.value }, event.target);
    }
  });

  list.addEventListener("click", (event) => {
    const deleteButton = event.target.closest("[data-sr-delete]");
    const detailsButton = event.target.closest("[data-sr-details]");

    if (deleteButton) {
      const order = orders.find(
        (item) => item.id === deleteButton.dataset.srDelete,
      );

      if (!order) return;

      if (!order.statusOptions.includes("CANCELADO")) {
        adminMessage = "Este pedido já foi encerrado e não pode ser cancelado.";
        adminMessageError = true;
        render();
        return;
      }

      if (!confirm(`Cancelar o pedido ${order.id} de ${order.client}?`)) {
        return;
      }
      const controle = list.querySelector(`[data-sr-status="${CSS.escape(order.id)}"]`);
      if (controle) {
        void enviarAcaoAdmin(order, "status", { status: "CANCELADO" }, controle);
      }
      return;
    }

    if (detailsButton) {
      const order = orders.find(
        (item) => item.id === detailsButton.dataset.srDetails,
      );

      if (order) openDetails(order);
    }
  });

  function openDetails(order) {
    const modal = document.createElement("dialog");
    modal.setAttribute("aria-labelledby", "sr-detail-title");

    const fields = [
      ["Cliente", order.client],
      ["Telefone", order.phone || "Não informado"],
      ["Serviço", order.equipment],
      ["Técnico", order.technician || "Não atribuído"],
      ["Agendamento", `${dateBR(order.date)} às ${order.time}`],
      ["Endereço", order.address || "Não informado"],
      ["Descrição", order.description || "Sem descrição"],
      [
        "Criado em",
        order.createdAt ? dateBR(order.createdAt) : "Não informado",
      ],
    ];

    const attachments = Array.isArray(order.attachments)
      ? order.attachments
      : [];

    modal.innerHTML = `
      <h2 id="sr-detail-title">Pedido ${escapeHTML(order.id)}</h2>

      <div class="sr-tags">
        ${statusBadge(order.status)}

        ${
          order.urgent
            ? `
          <span class="sr-badge sr-urgent">⚡ Urgente</span>
        `
            : ""
        }
      </div>

      <div class="sr-detail-grid">
        ${fields
          .map(
            ([label, value]) => `
          <div>
            <div class="sr-label">${label}</div>
            <div class="sr-description">${escapeHTML(value)}</div>
          </div>
        `,
          )
          .join("")}
      </div>

      ${
        attachments.length
          ? `
        <div class="sr-label">Arquivos registrados</div>
        <ul>
          ${attachments
            .map(
              (file) => `
            <li class="sr-description">
              ${escapeHTML(
                typeof file === "string" ? file : file?.name || "Arquivo",
              )}
            </li>
          `,
            )
            .join("")}
        </ul>
      `
          : ""
      }

      <div class="form-actions">
        <button class="primary" data-sr-close>Fechar</button>
      </div>
    `;

    document.body.appendChild(modal);

    modal.querySelector("[data-sr-close]").addEventListener("click", () => {
      modal.close();
    });

    modal.addEventListener("close", () => modal.remove(), { once: true });
    modal.showModal();
  }

  updateList();
}

function renderSchedule() {
  const STORAGE_KEY = "hvac-agenda-v1";
  const DEFAULT_TIMES = [
    "07:00",
    "08:00",
    "09:00",
    "10:00",
    "11:00",
    "13:00",
    "14:00",
    "15:00",
    "16:00",
    "17:00",
  ];

  // Preserva o mês e o dia selecionados ao atualizar a tela.
  if (!renderSchedule.state) {
    const reference = typeof TODAY === "string" ? TODAY : localDate();
    const [year, month] = reference.split("-").map(Number);

    let saved = {};

    try {
      const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY));

      if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
        for (const [date, slots] of Object.entries(parsed)) {
          if (
            /^\d{4}-\d{2}-\d{2}$/.test(date) &&
            slots &&
            typeof slots === "object" &&
            !Array.isArray(slots)
          ) {
            saved[date] = Object.fromEntries(
              Object.entries(slots).filter(
                ([time, status]) =>
                  /^([01]\d|2[0-3]):[0-5]\d$/.test(time) &&
                  ["free", "blocked", "removed"].includes(status),
              ),
            );
          }
        }
      }
    } catch {}

    renderSchedule.state = {
      selected: reference,
      year,
      month: month - 1,
      saved,
      error: "",
    };
  }

  const state = renderSchedule.state;

  if (!document.querySelector("#agenda-styles")) {
    const style = document.createElement("style");
    style.id = "agenda-styles";

    style.textContent = `
      .agenda-layout {
        display: grid;
        grid-template-columns: 306px minmax(0, 1fr);
        gap: 18px;
        align-items: start;
      }

      .agenda-calendar,
      .agenda-day {
        padding: 18px;
        border: 1px solid #30364c;
        border-radius: 15px;
        background: rgba(255,255,255,.055);
      }

      .agenda-calendar-header,
      .agenda-day-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 16px;
      }

      .agenda-calendar h2,
      .agenda-day h2 {
        margin: 0;
        font-size: 14px;
      }

      .agenda-month-nav {
        display: flex;
        gap: 4px;
      }

      .agenda-month-nav button {
        width: 25px;
        height: 27px;
        border-radius: 7px;
        color: #bac0d1;
        background: #ffffff09;
        font-size: 18px;
      }

      .agenda-weekdays,
      .agenda-days {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 3px;
      }

      .agenda-weekdays {
        margin-bottom: 8px;
        color: #80869c;
        text-align: center;
        font-size: 11px;
        font-weight: 700;
      }

      .agenda-weekdays span {
        padding: 6px 0;
      }

      .agenda-date {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 36px;
        padding: 0 0 4px;
        border-radius: 11px;
        color: #c9cede;
        background: transparent;
        font-size: 11px;
        font-weight: 700;
      }

      .agenda-date.has-slots { background: #ffffff09; }
      .agenda-date.weekend { color: #70788f; }

      .agenda-date.selected {
        color: #05132b;
        background: #00d8f3;
      }

      .agenda-date:hover:not(.selected) { background: #ffffff16; }

      .agenda-dots {
        position: absolute;
        bottom: 5px;
        display: flex;
        gap: 3px;
      }

      .agenda-dot {
        display: inline-block;
        width: 4px;
        height: 4px;
        border-radius: 50%;
      }

      .agenda-dot.free { background: #42dc83; }
      .agenda-dot.occupied { background: #ffab00; }
      .agenda-dot.blocked { background: #ff4c59; }

      .agenda-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 18px;
        color: #979eb3;
        font-size: 11px;
      }

      .agenda-legend span {
        display: flex;
        align-items: center;
        gap: 5px;
      }

      .agenda-legend .agenda-dot {
        width: 7px;
        height: 7px;
      }

      .agenda-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 7px;
        font-size: 11px;
      }

      .agenda-summary .free { color: #42dc83; }
      .agenda-summary .occupied { color: #ffab00; }
      .agenda-summary .blocked { color: #ff4c59; }

      .agenda-add {
        padding: 8px 11px;
        border-radius: 11px;
        background: #00d8f3;
        color: #05132b;
        white-space: nowrap;
      }

      .agenda-slots {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 7px;
      }

      .agenda-slot {
        padding: 12px 10px 10px;
        min-height: 75px;
        border: 1px solid;
        border-radius: 11px;
      }

      .agenda-slot.free {
        background: #42dc8320;
        border-color: #42dc8338;
      }

      .agenda-slot.occupied {
        background: #ffab002e;
        border-color: #ffab003b;
      }

      .agenda-slot.blocked {
        background: #ff4c591d;
        border-color: #ff4c5938;
      }

      .agenda-slot-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        margin-bottom: 10px;
      }

      .agenda-slot-header strong { font-size: 14px; }
      .agenda-slot-status { font-size: 11px; }
      .free .agenda-slot-status { color: #42dc83; }
      .occupied .agenda-slot-status { color: #ffab00; }
      .blocked .agenda-slot-status { color: #ff4c59; }

      .agenda-slot-actions {
        display: flex;
        gap: 6px;
      }

      .agenda-toggle {
        flex: 1;
        padding: 5px;
        border-radius: 7px;
        background: #ffffff1c;
        color: #d6e1e7;
        font-size: 11px;
        font-weight: 700;
      }

      .agenda-remove {
        display: grid;
        place-items: center;
        width: 24px;
        border-radius: 7px;
        background: #ff4c5922;
        color: #ff4c59;
        font-size: 18px;
      }

      .agenda-booking {
        margin: 0 0 6px;
        color: #d0c7bb;
        font-size: 11px;
        overflow-wrap: anywhere;
      }

      .agenda-locked {
        margin: 0;
        color: #aaa093;
        font-size: 11px;
      }

      .agenda-empty {
        grid-column: 1 / -1;
        padding: 25px 0;
        color: #929bb2;
      }

      @media (max-width: 1150px) {
        .agenda-slots {
          grid-template-columns: repeat(3, minmax(0, 1fr));
        }
      }

      @media (max-width: 900px) {
        .agenda-layout {
          grid-template-columns: 270px minmax(0, 1fr);
        }

        .agenda-slots {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }

      @media (max-width: 650px) {
        .agenda-layout { grid-template-columns: 1fr; }
        .agenda-date { height: 40px; }
      }
    `;

    document.head.appendChild(style);
  }

  function dateKey(year, month, day) {
    return [
      year,
      String(month + 1).padStart(2, "0"),
      String(day).padStart(2, "0"),
    ].join("-");
  }

  function bookingsFor(date) {
    return orders.filter(
      (order) =>
        order.date === date &&
        ["SOLICITADO", "AGENDADO", "EM_ANDAMENTO"].includes(order.statusCode),
    );
  }

  function slotsFor(date) {
    const [year, month, day] = date.split("-").map(Number);
    const weekday = new Date(year, month - 1, day).getDay();
    const changes = state.saved[date] || {};

    // Por padrão, cria horários de segunda a sexta.
    const slots = new Map(
      (weekday === 0 || weekday === 6 ? [] : DEFAULT_TIMES).map((time) => [
        time,
        { time, status: "free", orders: [] },
      ]),
    );

    for (const [time, status] of Object.entries(changes)) {
      if (status === "removed") {
        slots.delete(time);
      } else {
        slots.set(time, { time, status, orders: [] });
      }
    }

    // Reservas existentes sempre prevalecem sobre alterações locais.
    for (const order of bookingsFor(date)) {
      const current = slots.get(order.time);

      slots.set(order.time, {
        time: order.time,
        status: "occupied",
        orders: [...(current?.orders || []), order],
      });
    }

    return [...slots.values()].sort((a, b) => a.time.localeCompare(b.time));
  }

  function saveSlot(time, status) {
    if (bookingsFor(state.selected).some((order) => order.time === time)) {
      return;
    }

    state.saved[state.selected] ||= {};
    state.saved[state.selected][time] = status;

    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(state.saved));
      state.error = "";
    } catch {
      state.error =
        "Não foi possível salvar no navegador. As alterações valem apenas nesta sessão.";
    }

    renderSchedule();
  }

  const selectedSlots = slotsFor(state.selected);
  const count = (status) =>
    selectedSlots.filter((slot) => slot.status === status).length;

  const firstWeekday = new Date(state.year, state.month, 1).getDay();
  const totalDays = new Date(state.year, state.month + 1, 0).getDate();

  const monthText = new Date(state.year, state.month, 1)
    .toLocaleDateString("pt-BR", { month: "long", year: "numeric" })
    .replace(" de ", " ");

  const monthTitle = monthText.charAt(0).toUpperCase() + monthText.slice(1);
  const labels = { free: "Livre", occupied: "Ocupado", blocked: "Bloqueado" };

  content.innerHTML = `
    <section class="agenda-layout">
      <aside class="agenda-calendar" aria-label="Calendário">
        <div class="agenda-calendar-header">
          <h2>${escapeHTML(monthTitle)}</h2>

          <div class="agenda-month-nav">
            <button data-month="-1" aria-label="Mês anterior">‹</button>
            <button data-month="1" aria-label="Próximo mês">›</button>
          </div>
        </div>

        <div class="agenda-weekdays">
          ${["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"]
            .map((day) => `<span>${day}</span>`)
            .join("")}
        </div>

        <div class="agenda-days">
          ${'<span aria-hidden="true"></span>'.repeat(firstWeekday)}

          ${Array.from({ length: totalDays }, (_, index) => {
            const day = index + 1;
            const date = dateKey(state.year, state.month, day);
            const weekday = new Date(state.year, state.month, day).getDay();
            const slots = slotsFor(date);
            const presentStatuses = [
              ...new Set(slots.map((slot) => slot.status)),
            ];

            return `
              <button
                class="agenda-date
                  ${slots.length ? "has-slots" : ""}
                  ${weekday === 0 || weekday === 6 ? "weekend" : ""}
                  ${date === state.selected ? "selected" : ""}"
                data-date="${date}"
                aria-label="${dateBR(date)}"
                aria-pressed="${date === state.selected}"
              >
                ${day}

                <span class="agenda-dots" aria-hidden="true">
                  ${presentStatuses
                    .map(
                      (status) => `
                    <i class="agenda-dot ${status}"></i>
                  `,
                    )
                    .join("")}
                </span>
              </button>
            `;
          }).join("")}
        </div>

        <div class="agenda-legend">
          <span><i class="agenda-dot free"></i> Livre</span>
          <span><i class="agenda-dot occupied"></i> Ocupado</span>
          <span><i class="agenda-dot blocked"></i> Bloqueado</span>
        </div>
      </aside>

      <section class="agenda-day" aria-label="Horários do dia">
        <div class="agenda-day-header">
          <div>
            <h2>${dateBR(state.selected)}</h2>

            <div class="agenda-summary">
              <span class="free">${count("free")} livres</span>
              <span class="occupied">${count("occupied")} ocupados</span>
              <span class="blocked">${count("blocked")} bloqueados</span>
            </div>
          </div>

          <button class="agenda-add" id="agenda-add">+ Horário</button>
        </div>

        <div class="agenda-slots">
          ${
            selectedSlots
              .map(
                (slot) => `
            <article class="agenda-slot ${slot.status}">
              <div class="agenda-slot-header">
                <strong>${escapeHTML(slot.time)}</strong>
                <span class="agenda-slot-status">${labels[slot.status]}</span>
              </div>

              ${
                slot.status === "occupied"
                  ? `
                <p class="agenda-booking">
                  ${slot.orders.map((order) => escapeHTML(order.id)).join(", ")}
                </p>
                <p class="agenda-locked">Não pode ser alterado</p>
              `
                  : `
                <div class="agenda-slot-actions">
                  <button
                    class="agenda-toggle"
                    data-toggle-time="${escapeHTML(slot.time)}"
                    aria-label="${slot.status === "blocked" ? "Liberar" : "Bloquear"} ${escapeHTML(slot.time)}"
                  >
                    ${slot.status === "blocked" ? "🔓 Liberar" : "🔒 Bloquear"}
                  </button>

                  <button
                    class="agenda-remove"
                    data-remove-time="${escapeHTML(slot.time)}"
                    aria-label="Remover horário ${escapeHTML(slot.time)}"
                  >×</button>
                </div>
              `
              }
            </article>
          `,
              )
              .join("") ||
            `
            <div class="agenda-empty">
              Nenhum horário disponível. Clique em “+ Horário” para adicionar.
            </div>
          `
          }
        </div>

        ${
          state.error
            ? `
          <p class="notice" role="status">${escapeHTML(state.error)}</p>
        `
            : ""
        }
      </section>
    </section>
  `;

  const layout = content.querySelector(".agenda-layout");

  layout.addEventListener("click", (event) => {
    const button = event.target.closest("button");
    if (!button) return;

    if (button.dataset.month) {
      const next = new Date(
        state.year,
        state.month + Number(button.dataset.month),
        1,
      );

      state.year = next.getFullYear();
      state.month = next.getMonth();
      renderSchedule();
      return;
    }

    if (button.dataset.date) {
      state.selected = button.dataset.date;
      renderSchedule();
      return;
    }

    if (button.dataset.toggleTime) {
      const time = button.dataset.toggleTime;
      const slot = slotsFor(state.selected).find((item) => item.time === time);

      if (slot && slot.status !== "occupied") {
        saveSlot(time, slot.status === "blocked" ? "free" : "blocked");
      }
      return;
    }

    if (button.dataset.removeTime) {
      saveSlot(button.dataset.removeTime, "removed");
      return;
    }

    if (button.id === "agenda-add") {
      openAddTime();
    }
  });

  function openAddTime() {
    const selectedDate = state.selected;
    const modal = document.createElement("dialog");

    modal.setAttribute("aria-labelledby", "agenda-modal-title");

    modal.innerHTML = `
      <h2 id="agenda-modal-title">Adicionar horário</h2>
      <p class="muted">${dateBR(selectedDate)}</p>

      <form>
        <label>
          Horário
          <input type="time" name="time" required value="18:00">
        </label>

        <p class="notice" role="alert" data-error hidden></p>

        <div class="form-actions">
          <button type="button" class="secondary" data-cancel>Cancelar</button>
          <button type="submit" class="primary">Adicionar</button>
        </div>
      </form>
    `;

    document.body.appendChild(modal);

    modal.querySelector("[data-cancel]").addEventListener("click", () => {
      modal.close();
    });

    modal.querySelector("form").addEventListener("submit", (event) => {
      event.preventDefault();

      const time = modal.querySelector('[name="time"]').value;
      if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(time)) return;

      if (slotsFor(selectedDate).some((slot) => slot.time === time)) {
        const error = modal.querySelector("[data-error]");
        error.textContent = "Esse horário já existe neste dia.";
        error.hidden = false;
        return;
      }

      state.selected = selectedDate;
      saveSlot(time, "free");
      modal.close();
    });

    modal.addEventListener("close", () => modal.remove(), { once: true });
    modal.showModal();
  }
}

function renderPortfolio() {
  const completed = orders.filter((order) => order.status === "Concluído");
  content.innerHTML = `
        <div class="toolbar"><h1>Portfólio de serviços concluídos</h1></div>
        <section class="technicians">
          ${
            completed
              .map(
                (order) => `
            <article class="panel">
              <h2>${escapeHTML(order.equipment)}</h2>
              <p><strong>${escapeHTML(order.client)}</strong></p>
              <p class="muted">Técnico: ${escapeHTML(order.technician)}</p>
              <p class="muted">${dateBR(order.date)} · ${escapeHTML(order.id)}</p>
              ${badge(order.status)}
            </article>
          `,
              )
              .join("") || '<div class="empty">Nenhum serviço concluído.</div>'
          }
        </section>`;
}

function render() {
  const pending = orders.filter((order) => order.statusCode === "SOLICITADO").length;
  document.querySelector("#pending-count").textContent = pending;
  document.querySelector("#notification-count").textContent = pending;

  document.querySelectorAll(".tab").forEach((tab) => {
    const active = tab.dataset.page === page;
    tab.classList.toggle("active", active);
    if (active) tab.setAttribute("aria-current", "page");
    else tab.removeAttribute("aria-current");
  });

  if (adminLoading) {
    content.innerHTML = '<section class="panel"><div class="empty">Carregando solicitações do banco…</div></section>';
    return;
  }
  if (adminError) {
    content.innerHTML = `
      <section class="panel" role="status">
        <div class="empty">${escapeHTML(adminError)}</div>
        <button type="button" class="primary" id="retry-admin-load">Tentar novamente</button>
      </section>`;
    content.querySelector("#retry-admin-load").addEventListener("click", () => carregarSolicitacoesAdmin());
    return;
  }

  if (page === "overview") renderOverview();
  if (page === "requests") renderRequests();
  if (page === "history") renderRequests(true);
  if (page === "schedule") renderSchedule();
  if (page === "portfolio") renderPortfolio();

  if (adminMessage) {
    content.insertAdjacentHTML(
      "afterbegin",
      `<p class="notice" role="status" style="${adminMessageError ? "border-color:#ff6b7a;color:#ff9aa5" : ""}">${escapeHTML(adminMessage)}</p>`,
    );
  }
}

function navigate(nextPage) {
  page = nextPage;
  search = "";
  filter = "Todos";
  render();
}

document.querySelectorAll(".tab").forEach((tab) => {
  tab.addEventListener("click", () => navigate(tab.dataset.page));
});

document.querySelector("#notifications").addEventListener("click", () => {
  page = "requests";
  search = "";
  filter = "Solicitado";
  render();
});

document.querySelector("#new-order").addEventListener("click", () => {
  form.reset();
  form.elements.date.value = TODAY;
  form.elements.time.value = "09:00";
  dialog.showModal();
});

document.querySelector("#cancel-order").addEventListener("click", () => {
  dialog.close();
});

form.addEventListener("submit", (event) => {
  event.preventDefault();
  dialog.close();
  adminMessage = "O cadastro manual de pedidos ainda não está ligado ao banco. Os pedidos feitos pelos clientes aparecem nesta lista automaticamente.";
  adminMessageError = false;
  render();
});

form.elements.client.addEventListener("input", () => {
  form.elements.client.setCustomValidity("");
});

// A saída é enviada por POST ao PHP para encerrar a sessão.

render();
void carregarSolicitacoesAdmin();
