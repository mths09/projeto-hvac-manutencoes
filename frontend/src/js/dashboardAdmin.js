const API = "../../../backend/solicitacoes_admin.php";
const AGENDA_API = "../../../backend/agenda_admin.php";
const PORTFOLIO_API = "../../../backend/portfolio_admin.php";
const TECH_API = "../../../backend/tecnicos_admin.php";
const ADMIN_NOTIFICATIONS_API = "../../../backend/notificacoes_admin.php";
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
let serviceTypes = [];
let page = "overview";
let search = "";
let filter = "Todos";
let adminCsrf = "";
let adminLoading = true;
let adminError = "";
let adminMessage = "";
let adminMessageError = false;
let portfolioItems = [];
let portfolioCategories = [];
let portfolioFilter = "Todos";
let portfolioLoaded = false;
let portfolioLoading = false;
let portfolioError = "";
let adminNotifications = [];
let notificationsCsrf = "";
let techniciansAdmin = [];
let technicianCsrf = "";
let techniciansLoading = false;
let techniciansError = "";
let techniciansLoaded = false;

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
    status:
      STATUS_LABELS[codigoStatus] || String(pedido.status_nome || codigoStatus),
    statusCode: codigoStatus,
    statusOptions: Array.isArray(pedido.status_permitidos)
      ? pedido.status_permitidos.filter((codigo) => STATUS_LABELS[codigo])
      : [],
    urgent: Boolean(pedido.urgente),
    createdAt: criado.slice(0, 10),
    description: String(pedido.descricao || ""),
    address: String(pedido.endereco || ""),
    triage: Array.isArray(pedido.triagem)
      ? pedido.triagem.map((item) => ({
          question: String(item.pergunta || ""),
          answer: String(item.resposta || ""),
        }))
      : [],
    totalDocuments: Number(pedido.total_documentos) || 0,
    attachments: Array.isArray(pedido.anexos)
      ? pedido.anexos.map((anexo) => ({
          id: String(anexo.id),
          mime: String(anexo.mime),
          legenda: String(anexo.legenda || ""),
        }))
      : [],
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
    if (
      !resposta.ok ||
      dados?.sucesso !== true ||
      !Array.isArray(dados.pedidos) ||
      !Array.isArray(dados.tecnicos) ||
      !Array.isArray(dados.servicos)
    ) {
      throw new Error(
        dados?.mensagem || "Não foi possível carregar as solicitações.",
      );
    }
    if (typeof dados.csrf !== "string") {
      throw new Error(
        "O servidor não forneceu a proteção de sessão do painel.",
      );
    }
    orders = dados.pedidos.map(normalizarPedido);
    technicians = dados.tecnicos.map((tecnico) => ({
      id: String(tecnico.id),
      name: String(tecnico.nome),
      initials: iniciais(tecnico.nome),
      specialty: "Equipe técnica",
    }));
    serviceTypes = dados.servicos.map((servico) => ({
      id: String(servico.id),
      name: String(servico.nome),
    }));
    adminCsrf = dados.csrf;
    adminError = "";
    adminLoading = false;
    if (mostrarCarregamento) render();
    void carregarNotificacoesAdmin();
  } catch (erro) {
    adminLoading = false;
    adminError =
      "Não foi possível carregar as solicitações do banco. Verifique o servidor e tente novamente.";
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
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        csrf: adminCsrf,
        protocolo: pedido.id,
        acao,
        ...valores,
      }),
    });
    const dados = await resposta.json();
    if (resposta.status === 401 || resposta.status === 403) {
      window.location.replace("./login.html");
      return;
    }
    if (!resposta.ok || dados?.sucesso !== true) {
      throw new Error(
        dados?.mensagem || "Não foi possível atualizar o pedido.",
      );
    }
    adminMessage = dados.mensagem || "Alteração salva.";
    if (renderSchedule.state) {
      renderSchedule.state.loadedMonth = "";
      renderSchedule.state.requestedMonth = "";
    }
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
  const partes = String(nome || "")
    .trim()
    .split(/\s+/)
    .filter(Boolean);
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
    orders.filter(
      (order) =>
        order.urgent && !["CONCLUIDO", "CANCELADO"].includes(order.statusCode),
    ),
  );
  const pending = orders.filter(
    (order) => order.statusCode === "SOLICITADO",
  ).length;
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
    const statusHistorico = content.querySelector("#history-status")?.value || "Todos";
    const servicoHistorico = content.querySelector("#history-service")?.value || "Todos";
    const tecnicoHistorico = content.querySelector("#history-technician")?.value || "Todos";
    const dataInicial = content.querySelector("#history-date-from")?.value || "";
    const dataFinal = content.querySelector("#history-date-to")?.value || "";
    const textoBusca = history
      ? content.querySelector("#history-search")?.value || ""
      : search;
    const matchesPage = history
      ? ["CONCLUIDO", "CANCELADO"].includes(order.statusCode)
      : order.statusCode !== "CONCLUIDO";
    const matchesSearch = [
      order.id,
      order.client,
      order.equipment,
      order.technician,
    ]
      .join(" ")
      .toLocaleLowerCase("pt-BR")
      .includes(textoBusca.toLocaleLowerCase("pt-BR"));
    const matchesStatus = !history || statusHistorico === "Todos" || order.statusCode === statusHistorico;
    const matchesService = !history || servicoHistorico === "Todos" || order.equipment === servicoHistorico;
    const matchesTechnician = !history || tecnicoHistorico === "Todos" || (order.technician || "Não atribuído") === tecnicoHistorico;
    const matchesStart = !history || !dataInicial || order.date >= dataInicial;
    const matchesEnd = !history || !dataFinal || order.date <= dataFinal;
    return (
      matchesPage &&
      matchesSearch &&
      matchesStatus &&
      matchesService &&
      matchesTechnician &&
      matchesStart &&
      matchesEnd &&
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
      </div>

      <section class="panel history-filters" aria-label="Filtros do histórico">
        <label>Buscar
          <input id="history-search" placeholder="Cliente, protocolo, serviço ou técnico..." aria-label="Buscar no histórico">
        </label>
        <label>Status
          <select id="history-status">
            <option value="Todos">Todos</option>
            <option value="CONCLUIDO">Concluído</option>
            <option value="CANCELADO">Cancelado</option>
          </select>
        </label>
        <label>Serviço
          <select id="history-service">
            <option value="Todos">Todos</option>
            ${[...new Set(orders.map((order) => order.equipment))].sort((a,b) => a.localeCompare(b, "pt-BR")).map((nome) => `<option value="${escapeHTML(nome)}">${escapeHTML(nome)}</option>`).join("")}
          </select>
        </label>
        <label>Técnico
          <select id="history-technician">
            <option value="Todos">Todos</option>
            ${[...new Set(orders.map((order) => order.technician || "Não atribuído"))].sort((a,b) => a.localeCompare(b, "pt-BR")).map((nome) => `<option value="${escapeHTML(nome)}">${escapeHTML(nome)}</option>`).join("")}
          </select>
        </label>
        <label>De
          <input type="date" id="history-date-from" aria-label="Data inicial do histórico">
        </label>
        <label>Até
          <input type="date" id="history-date-to" aria-label="Data final do histórico">
        </label>
        <button type="button" class="secondary history-clear" id="history-clear">Limpar filtros</button>
      </section>

      <section class="panel" id="history-results">
        ${table(filteredOrders(true), false)}
      </section>
    `;

    const atualizarHistorico = () => {
        content.querySelector("#history-results").innerHTML = table(
          filteredOrders(true),
          false,
        );
    };
    content.querySelectorAll("#history-search, #history-status, #history-service, #history-technician, #history-date-from, #history-date-to")
      .forEach((campo) => campo.addEventListener(campo.tagName === "INPUT" && campo.type === "text" ? "input" : "change", atualizarHistorico));
    content.querySelector("#history-clear").addEventListener("click", () => {
      content.querySelectorAll("#history-search, #history-status, #history-service, #history-technician, #history-date-from, #history-date-to")
        .forEach((campo) => { campo.value = campo.tagName === "SELECT" ? "Todos" : ""; });
      atualizarHistorico();
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

  const activeOrders = orders.filter(
    (order) => order.statusCode !== "CONCLUIDO",
  );

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
    const statusOptions = [
      ...new Set([order.statusCode, ...order.statusOptions]),
    ];

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
                .map(
                  (codigo) => `
                <option
                  value="${escapeHTML(codigo)}"
                  ${order.statusCode === codigo ? "selected" : ""}
                >${escapeHTML(STATUS_LABELS[codigo])}</option>
              `,
                )
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
                .map(
                  (tecnico) => `
                <option
                  value="${escapeHTML(tecnico.id)}"
                  ${tecnico.id === order.technicianId ? "selected" : ""}
                >${escapeHTML(tecnico.name)}</option>
              `,
                )
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
      void enviarAcaoAdmin(
        order,
        "status",
        { status: event.target.value },
        event.target,
      );
    } else {
      void enviarAcaoAdmin(
        order,
        "tecnico",
        { tecnico_id: event.target.value },
        event.target,
      );
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
      const controle = list.querySelector(
        `[data-sr-status="${CSS.escape(order.id)}"]`,
      );
      if (controle) {
        void enviarAcaoAdmin(
          order,
          "status",
          { status: "CANCELADO" },
          controle,
        );
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
    const triage = Array.isArray(order.triage) ? order.triage : [];

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
        triage.length
          ? `
        <div class="sr-label">Respostas da triagem</div>
        <ul class="sr-triage-list">
          ${triage
            .map(
              (item) => `
              <li class="sr-description"><strong>${escapeHTML(item.question)}</strong><br>${escapeHTML(item.answer)}</li>
            `,
            )
            .join("")}
        </ul>
      `
          : ""
      }

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
  // O banco é a fonte da agenda; o navegador guarda apenas o mês selecionado.
  if (!renderSchedule.state) {
    const reference = typeof TODAY === "string" ? TODAY : localDate();
    const [year, month] = reference.split("-").map(Number);
    renderSchedule.state = {
      selected: reference,
      year,
      month: month - 1,
      slots: [],
      loadedMonth: "",
      requestedMonth: "",
      loading: false,
      error: "",
      notice: "",
      busy: false,
      addingTime: false,
    };
  }

  const state = renderSchedule.state;
  const monthKey = `${state.year}-${String(state.month + 1).padStart(2, "0")}`;

  if (state.loadedMonth !== monthKey && state.requestedMonth !== monthKey) {
    state.requestedMonth = monthKey;
    state.loading = true;
    state.error = "";
    fetch(`${AGENDA_API}?ano=${state.year}&mes=${state.month + 1}`, {
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" },
    })
      .then(async (resposta) => {
        const dados = await resposta.json();
        if (resposta.status === 401 || resposta.status === 403) {
          window.location.replace("./login.html");
          throw new Error("Sessão administrativa encerrada.");
        }
        if (
          !resposta.ok ||
          dados?.sucesso !== true ||
          !Array.isArray(dados.horarios)
        ) {
          throw new Error(
            dados?.mensagem || "Não foi possível carregar a agenda.",
          );
        }
        if (typeof dados.csrf !== "string") {
          throw new Error(
            "O servidor não forneceu a proteção de sessão do painel.",
          );
        }
        if (state.requestedMonth !== monthKey) return;
        adminCsrf = dados.csrf;
        state.slots = dados.horarios.map((horario) => ({
          id: String(horario.id),
          date: String(horario.inicio || "").slice(0, 10),
          time: String(horario.inicio || "").slice(11, 16),
          endTime: String(horario.fim || "").slice(11, 16),
          status:
            horario.estado === "blocked"
              ? "blocked"
              : horario.estado === "full"
                ? "occupied"
                : "free",
          capacity: Number(horario.capacidade) || 1,
          bookings: Number(horario.reservas) || 0,
          protocols: Array.isArray(horario.protocolos)
            ? horario.protocolos.map(String)
            : [],
        }));
        state.loadedMonth = monthKey;
        state.error = "";
      })
      .catch((erro) => {
        if (
          state.requestedMonth !== monthKey ||
          erro.message === "Sessão administrativa encerrada."
        )
          return;
        state.loadedMonth = monthKey;
        state.error = erro.message || "Não foi possível carregar a agenda.";
      })
      .finally(() => {
        if (state.requestedMonth !== monthKey) return;
        state.loading = false;
        renderSchedule();
      });
  }

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

      .agenda-refresh {
        padding: 8px 10px;
        border: 1px solid #ffffff20;
        border-radius: 10px;
        color: #c9cede;
        background: #ffffff0b;
        white-space: nowrap;
      }

      .agenda-add-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 7px;
        margin-bottom: 12px;
        padding: 9px 10px;
        border: 1px solid #00d8f344;
        border-radius: 10px;
        background: #00d8f314;
      }

      .agenda-add-row select,
      .agenda-add-row input {
        min-height: 28px;
        padding: 4px 9px;
        border: 1px solid #ffffff28;
        border-radius: 7px;
        color: #f2f5ff;
        background: #252d43;
        font-size: 11px;
      }

      .agenda-add-row select { min-width: 76px; }

      .agenda-add-options {
        position: relative;
        color: #c9cede;
        font-size: 11px;
      }

      .agenda-add-options summary {
        cursor: pointer;
        list-style: none;
        white-space: nowrap;
      }

      .agenda-add-options summary::-webkit-details-marker { display: none; }

      .agenda-add-options[open] .agenda-add-fields {
        position: absolute;
        z-index: 2;
        top: 25px;
        left: 0;
        display: flex;
        gap: 8px;
        min-width: 310px;
        padding: 10px;
        border: 1px solid #3a4662;
        border-radius: 9px;
        background: #171f35;
        box-shadow: 0 8px 24px #0005;
      }

      .agenda-add-fields label {
        display: grid;
        gap: 4px;
      }

      .agenda-cancel {
        padding: 6px 9px;
        color: #b8c0d2;
        background: transparent;
      }

      .agenda-slot-meta {
        margin: 0 0 8px;
        color: #aab2c6;
        font-size: 11px;
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

  function slotsFor(date) {
    return state.slots.filter((slot) => slot.date === date);
  }

  function dateAfterDays(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return dateKey(date.getFullYear(), date.getMonth(), date.getDate());
  }

  async function enviarAcaoAgenda(acao, valores) {
    if (!adminCsrf || state.busy) return;
    state.busy = true;
    state.error = "";
    state.notice = "";
    renderSchedule();
    try {
      const resposta = await fetch(AGENDA_API, {
        method: "POST",
        credentials: "same-origin",
        cache: "no-store",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ csrf: adminCsrf, acao, ...valores }),
      });
      const dados = await resposta.json();
      if (resposta.status === 401 || resposta.status === 403) {
        window.location.replace("./login.html");
        return;
      }
      if (!resposta.ok || dados?.sucesso !== true) {
        throw new Error(
          dados?.mensagem || "Não foi possível atualizar a agenda.",
        );
      }
      state.notice = dados.mensagem || "Agenda atualizada.";
      state.addingTime = false;
      state.loadedMonth = "";
      state.requestedMonth = "";
      state.busy = false;
      renderSchedule();
    } catch (erro) {
      state.error = erro.message || "Não foi possível atualizar a agenda.";
      state.busy = false;
      renderSchedule();
    }
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
  const labels = { free: "Livre", occupied: "Lotado", blocked: "Bloqueado" };
  const horariosPadrao = [
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
              <span class="occupied">${count("occupied")} lotados</span>
              <span class="blocked">${count("blocked")} bloqueados</span>
            </div>
          </div>

          <button class="agenda-refresh" id="agenda-refresh" ${state.busy || state.loading ? "disabled" : ""}>Atualizar</button>
          <button class="agenda-add" id="agenda-add" ${state.busy || state.loading || state.selected < localDate() || state.selected > dateAfterDays(30) ? "disabled" : ""}>+ Horário</button>
        </div>

        ${
          state.addingTime
            ? `
          <form class="agenda-add-row" id="agenda-add-form">
            <select name="inicio" aria-label="Horário de início" required ${state.busy || state.loading ? "disabled" : ""}>
              ${horariosPadrao.map((hora) => `<option value="${hora}" ${hora === "07:00" ? "selected" : ""}>${hora}</option>`).join("")}
            </select>
            <details class="agenda-add-options">
              <summary aria-label="Configurar fim e capacidade" title="Configurar fim e capacidade">⚙ Opções</summary>
              <div class="agenda-add-fields">
                <label>Início<input type="time" name="inicioPersonalizado" ${state.busy || state.loading ? "disabled" : ""}></label>
                <label>Fim<input type="time" name="fim" value="08:00" required ${state.busy || state.loading ? "disabled" : ""}></label>
                <label>Capacidade<input type="number" name="capacidade" value="1" min="1" max="100" step="1" required ${state.busy || state.loading ? "disabled" : ""}></label>
              </div>
            </details>
            <button class="agenda-add" type="submit" ${state.busy || state.loading ? "disabled" : ""}>Adicionar</button>
            <button class="agenda-cancel" type="button" data-cancel-add ${state.busy || state.loading ? "disabled" : ""}>Cancelar</button>
          </form>
        `
            : ""
        }

        <div class="agenda-slots">
          ${
            state.loading
              ? '<div class="agenda-empty">Carregando horários do banco…</div>'
              : !selectedSlots.length && state.error
                ? `<div class="agenda-empty" role="status">${escapeHTML(state.error)}<br><button class="agenda-refresh" data-retry>↻ Tentar novamente</button></div>`
                : selectedSlots.length
                  ? selectedSlots
                      .map(
                        (slot) => `
            <article class="agenda-slot ${slot.status}">
              <div class="agenda-slot-header">
                <strong>${escapeHTML(slot.time)}–${escapeHTML(slot.endTime)}</strong>
                <span class="agenda-slot-status">${labels[slot.status]}</span>
              </div>

              <p class="agenda-slot-meta">${slot.bookings} de ${slot.capacity} vagas ocupadas</p>
              ${slot.protocols.length ? `<p class="agenda-booking">${slot.protocols.map(escapeHTML).join(", ")}</p>` : ""}
              ${
                slot.bookings > 0 && slot.status !== "blocked"
                  ? '<p class="agenda-locked">Não pode ser bloqueado ou removido enquanto houver reservas ativas.</p>'
                  : slot.status === "occupied"
                    ? '<p class="agenda-locked">Capacidade atingida.</p>'
                    : `
                <div class="agenda-slot-actions">
                  <button
                    class="agenda-toggle"
                    data-toggle-id="${escapeHTML(slot.id)}"
                    ${state.busy ? "disabled" : ""}
                    aria-label="${slot.status === "blocked" ? "Liberar" : "Bloquear"} ${escapeHTML(slot.time)}"
                  >
                    ${slot.status === "blocked" ? "🔓 Liberar" : "🔒 Bloquear"}
                  </button>

                  ${slot.bookings === 0 ? `<button class="agenda-remove" data-remove-id="${escapeHTML(slot.id)}" ${state.busy ? "disabled" : ""} aria-label="Remover horário ${escapeHTML(slot.time)}">×</button>` : ""}
                </div>
              `
              }
            </article>
          `,
                      )
                      .join("")
                  : `
            <div class="agenda-empty">
              Nenhum horário disponível. Clique em “+ Horário” para adicionar.
            </div>
          `
          }
        </div>

        ${state.notice ? `<p class="notice" role="status">${escapeHTML(state.notice)}</p>` : ""}
        ${state.error && !state.loading && selectedSlots.length ? `<p class="notice error" role="alert">${escapeHTML(state.error)}</p>` : ""}
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
      const dia = Math.min(
        Number(state.selected.slice(8, 10)),
        new Date(state.year, state.month + 1, 0).getDate(),
      );
      state.selected = dateKey(state.year, state.month, dia);
      renderSchedule();
      return;
    }

    if (button.dataset.date) {
      state.selected = button.dataset.date;
      state.addingTime = false;
      renderSchedule();
      return;
    }

    if (button.dataset.cancelAdd !== undefined) {
      state.addingTime = false;
      renderSchedule();
      return;
    }

    if (button.dataset.toggleId) {
      const slot = state.slots.find(
        (item) => item.id === button.dataset.toggleId,
      );
      if (slot) {
        const acao = slot.status === "blocked" ? "liberar" : "bloquear";
        const verbo = acao === "liberar" ? "liberar" : "bloquear";
        if (
          window.confirm(
            `Deseja ${verbo} o horário ${slot.time}–${slot.endTime}?`,
          )
        ) {
          enviarAcaoAgenda(acao, { horario_id: slot.id });
        }
      }
      return;
    }

    if (button.dataset.removeId) {
      const slot = state.slots.find(
        (item) => item.id === button.dataset.removeId,
      );
      if (
        slot &&
        window.confirm(
          `Remover o horário ${slot.time}–${slot.endTime} da agenda?`,
        )
      ) {
        enviarAcaoAgenda("remover", { horario_id: slot.id });
      }
      return;
    }

    if (button.id === "agenda-add") {
      state.addingTime = !state.addingTime;
      state.error = "";
      renderSchedule();
      return;
    }

    if (button.id === "agenda-refresh" || button.dataset.retry !== undefined) {
      state.loadedMonth = "";
      state.requestedMonth = "";
      state.notice = "";
      renderSchedule();
    }
  });

  const addForm = layout.querySelector("#agenda-add-form");
  if (addForm) {
    const inicioInput = addForm.elements.inicio;
    const inicioPersonalizado = addForm.elements.inicioPersonalizado;
    const fimInput = addForm.elements.fim;
    const atualizarFimPadrao = (valor) => {
      if (fimInput.dataset.editado === "true" || !valor) return;
      const partes = valor.split(":").map(Number);
      if (partes[0] < 23)
        fimInput.value =
          String(partes[0] + 1).padStart(2, "0") +
          ":" +
          String(partes[1]).padStart(2, "0");
    };
    inicioInput.addEventListener("change", () => {
      inicioPersonalizado.value = "";
      atualizarFimPadrao(inicioInput.value);
    });
    inicioPersonalizado.addEventListener("input", () =>
      atualizarFimPadrao(inicioPersonalizado.value),
    );
    fimInput.addEventListener("input", () => {
      fimInput.dataset.editado = "true";
    });
    addForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (!addForm.reportValidity()) return;
      const formData = new FormData(addForm);
      const valores = {
        data: state.selected,
        inicio: String(
          formData.get("inicioPersonalizado") || formData.get("inicio") || "",
        ),
        fim: String(formData.get("fim") || ""),
        capacidade: String(formData.get("capacidade") || "1"),
      };
      if (valores.fim <= valores.inicio) {
        state.error = "O horário final precisa ser posterior ao início.";
        renderSchedule();
        return;
      }
      enviarAcaoAgenda("criar", valores);
    });
  }
}

async function carregarPortfolioAdmin() {
  portfolioLoading = true;
  portfolioError = "";
  if (page === "portfolio") renderPortfolio();
  try {
    const resposta = await fetch(PORTFOLIO_API, {
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" },
    });
    const dados = await resposta.json();
    if (resposta.status === 401 || resposta.status === 403) {
      window.location.replace("./login.html");
      return;
    }
    if (
      !resposta.ok ||
      dados?.sucesso !== true ||
      !Array.isArray(dados.categorias) ||
      !Array.isArray(dados.itens)
    ) {
      throw new Error(
        dados?.mensagem || "Não foi possível carregar o portfólio.",
      );
    }
    if (typeof dados.csrf === "string") adminCsrf = dados.csrf;
    portfolioCategories = dados.categorias.map((categoria) => ({
      id: String(categoria.id),
      name: String(categoria.nome),
      color: String(categoria.cor || "#00d8f3"),
    }));
    portfolioItems = dados.itens.map((item) => ({
      id: String(item.id),
      categoryId: String(item.id_categoria),
      category: String(item.categoria || "Sem categoria"),
      title: String(item.titulo || ""),
      description: String(item.descricao || ""),
      location: String(item.localidade_publica || ""),
      image: String(item.imagem_url || ""),
      imageName: String(item.imagem_nome || ""),
      published: Boolean(item.publicado),
      createdAt: String(item.criado_em || "").slice(0, 10),
    }));
    portfolioLoaded = true;
  } catch (erro) {
    portfolioError =
      erro.message || "Não foi possível carregar o portfólio do banco.";
  } finally {
    portfolioLoading = false;
    if (page === "portfolio") renderPortfolio();
  }
}

async function enviarPortfolio(formData) {
  const resposta = await fetch(PORTFOLIO_API, {
    method: "POST",
    credentials: "same-origin",
    cache: "no-store",
    headers: { Accept: "application/json" },
    body: formData,
  });
  const dados = await resposta.json();
  if (resposta.status === 401 || resposta.status === 403) {
    window.location.replace("./login.html");
    throw new Error("Entre como administrador para continuar.");
  }
  if (!resposta.ok || dados?.sucesso !== true)
    throw new Error(dados?.mensagem || "Não foi possível salvar o item.");
  return dados;
}

function abrirPortfolioModal(item = null) {
  const modal = document.createElement("dialog");
  modal.className = "portfolio-dialog";
  modal.setAttribute("aria-labelledby", "portfolio-dialog-title");
  const editando = Boolean(item);
  modal.innerHTML = `
    <h2 id="portfolio-dialog-title">${editando ? "Editar item" : "Adicionar item ao portfólio"}</h2>
    <form enctype="multipart/form-data">
      <label>Título do serviço<input name="titulo" required maxlength="150" value="${escapeHTML(item?.title || "")}" placeholder="Ex.: Instalação Split Residencial"></label>
      <label>Categoria<select name="id_categoria" required>${portfolioCategories.map((categoria) => `<option value="${escapeHTML(categoria.id)}" ${item?.categoryId === categoria.id ? "selected" : ""}>${escapeHTML(categoria.name)}</option>`).join("")}</select></label>
      <label>Imagem de capa<input name="imagem" type="file" accept="image/png,image/jpeg,image/webp" ${editando ? "" : "required"}><small>JPG, PNG ou WebP, até 2 MB.${item?.imageName ? ` Imagem atual: ${escapeHTML(item.imageName)}.` : ""}</small></label>
      <label>Localidade<input name="localidade" maxlength="150" value="${escapeHTML(item?.location || "")}" placeholder="Ex.: Sorocaba, SP"></label>
      <label>Descrição<textarea name="descricao" rows="4" maxlength="3000" placeholder="Conte um pouco sobre o serviço">${escapeHTML(item?.description || "")}</textarea></label>
      <label class="checkbox"><input name="publicado" type="checkbox" value="1" ${!editando || item?.published ? "checked" : ""}> Exibir no site</label>
      <p class="portfolio-form-error" role="alert" hidden></p>
      <div class="form-actions"><button type="button" class="secondary" data-cancel>Cancelar</button><button type="submit" class="primary">${editando ? "Salvar alterações" : "Adicionar item"}</button></div>
    </form>`;
  document.body.appendChild(modal);
  modal
    .querySelector("[data-cancel]")
    .addEventListener("click", () => modal.close());
  modal.querySelector("form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const formElement = event.currentTarget;
    const submit = formElement.querySelector('[type="submit"]');
    const error = formElement.querySelector(".portfolio-form-error");
    const dados = new FormData(formElement);
    dados.set("acao", "salvar");
    dados.set("csrf", adminCsrf);
    if (editando) dados.set("id", item.id);
    dados.set("publicado", formElement.elements.publicado.checked ? "1" : "0");
    submit.disabled = true;
    error.hidden = true;
    try {
      const resultado = await enviarPortfolio(dados);
      modal.close();
      adminMessage = resultado.mensagem || "Item salvo.";
      adminMessageError = false;
      portfolioLoaded = false;
      await carregarPortfolioAdmin();
      if (page === "portfolio") render();
    } catch (erro) {
      error.textContent = erro.message || "Não foi possível salvar o item.";
      error.hidden = false;
      submit.disabled = false;
    }
  });
  modal.addEventListener("close", () => modal.remove(), { once: true });
  modal.showModal();
}

async function carregarTecnicosAdmin(mostrarCarregamento = true) {
  if (mostrarCarregamento) {
    techniciansLoading = true;
    techniciansError = "";
    render();
  }
  try {
    const resposta = await fetch(TECH_API, {
      credentials: "same-origin", cache: "no-store", headers: { Accept: "application/json" },
    });
    const dados = await resposta.json();
    if (resposta.status === 401 || resposta.status === 403) {
      window.location.replace("./login.html");
      return;
    }
    if (!resposta.ok || dados?.sucesso !== true || !Array.isArray(dados.tecnicos)) {
      throw new Error(dados?.mensagem || "Não foi possível carregar os técnicos.");
    }
    techniciansAdmin = dados.tecnicos;
    technicianCsrf = String(dados.csrf || "");
    techniciansLoaded = true;
    techniciansError = "";
  } catch (erro) {
    techniciansError = erro.message || "Falha ao carregar técnicos.";
  } finally {
    techniciansLoading = false;
    if (page === "technicians") render();
  }
}

function renderTechnicians() {
  if (techniciansLoading) {
    content.innerHTML = '<section class="panel"><div class="empty">Carregando técnicos…</div></section>';
    return;
  }
  if (techniciansError) {
    content.innerHTML = `<section class="panel"><div class="empty">${escapeHTML(techniciansError)}</div><button class="primary" id="retry-technicians">Tentar novamente</button></section>`;
    content.querySelector("#retry-technicians").addEventListener("click", () => void carregarTecnicosAdmin());
    return;
  }
  const ativos = techniciansAdmin.filter((t) => t.ativo).length;
  content.innerHTML = `
    <div class="toolbar technicians-toolbar">
      <div><h1>Técnicos</h1><p>${ativos} ativos · ${techniciansAdmin.length} cadastrados</p></div>
      <button type="button" class="primary" id="add-technician">+ Novo Técnico</button>
    </div>
    <section class="technician-admin-list">
      ${techniciansAdmin.map((tecnico) => `
        <article class="panel technician-admin-card">
          <div class="technician-admin-identity"><div class="avatar">${escapeHTML(iniciais(tecnico.nome))}</div><div><strong>${escapeHTML(tecnico.nome)}</strong><small>${escapeHTML(tecnico.email || "Sem conta de acesso")}</small><small>${escapeHTML(tecnico.telefone)}</small></div></div>
          <span class="sr-badge ${tecnico.ativo ? "sr-done" : "sr-cancelled"}">${tecnico.ativo ? "Ativo" : "Inativo"}</span>
          <div class="technician-admin-actions"><button type="button" class="secondary" data-edit-technician="${escapeHTML(tecnico.id)}">Editar</button><button type="button" class="secondary" data-toggle-technician="${escapeHTML(tecnico.id)}" data-active="${tecnico.ativo ? "1" : "0"}">${tecnico.ativo ? "Desativar" : "Ativar"}</button></div>
        </article>`).join("") || '<div class="panel empty">Nenhum técnico cadastrado.</div>'}
    </section>`;
  content.querySelector("#add-technician").addEventListener("click", () => abrirDialogTecnico());
  content.querySelectorAll("[data-edit-technician]").forEach((botao) => botao.addEventListener("click", () => {
    const tecnico = techniciansAdmin.find((item) => item.id === botao.dataset.editTechnician);
    if (tecnico) abrirDialogTecnico(tecnico);
  }));
  content.querySelectorAll("[data-toggle-technician]").forEach((botao) => botao.addEventListener("click", () => void alternarAtivoTecnico(botao.dataset.toggleTechnician, botao.dataset.active !== "1")));
}

function abrirDialogTecnico(tecnico = null) {
  const formTecnico = document.querySelector("#technician-form");
  formTecnico.reset();
  formTecnico.elements.technician_id.value = tecnico?.id || "";
  formTecnico.elements.name.value = tecnico?.nome || "";
  formTecnico.elements.email.value = tecnico?.email || "";
  formTecnico.elements.phone.value = tecnico?.telefone || "";
  formTecnico.elements.password.required = !tecnico;
  document.querySelector("#technician-dialog-title").textContent = tecnico ? "Editar técnico" : "Novo técnico";
  document.querySelector("#save-technician").textContent = tecnico ? "Salvar alterações" : "Criar acesso";
  document.querySelector("#technician-feedback").hidden = true;
  document.querySelector("#technician-dialog").showModal();
}

async function alternarAtivoTecnico(id, ativo) {
  if (!technicianCsrf) return;
  if (!ativo && !window.confirm("Desativar este técnico? Ele não poderá mais entrar no sistema.")) return;
  try {
    const resposta = await fetch(TECH_API, {
      method: "POST", credentials: "same-origin", cache: "no-store",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ acao: "ativo", id, ativo, csrf: technicianCsrf }),
    });
    const dados = await resposta.json();
    if (!resposta.ok || dados?.sucesso !== true) throw new Error(dados?.mensagem || "Não foi possível atualizar o técnico.");
    adminMessage = dados.mensagem;
    adminMessageError = false;
    await carregarTecnicosAdmin(false);
  } catch (erro) {
    adminMessage = erro.message || "Falha ao atualizar técnico.";
    adminMessageError = true;
    render();
  }
}

function renderPortfolio() {
  if (!portfolioLoaded && !portfolioLoading && !portfolioError)
    void carregarPortfolioAdmin();
  const categorias = [
    { id: "Todos", name: "Todos", count: portfolioItems.length },
    ...portfolioCategories.map((categoria) => ({
      ...categoria,
      count: portfolioItems.filter((item) => item.categoryId === categoria.id)
        .length,
    })),
  ];
  if (!categorias.some((categoria) => categoria.id === portfolioFilter))
    portfolioFilter = "Todos";
  const itensVisiveis = portfolioItems.filter(
    (item) =>
      portfolioFilter === "Todos" || item.categoryId === portfolioFilter,
  );

  content.innerHTML = `
    <section class="portfolio-admin" aria-label="Gerenciamento do portfólio">
      <div class="portfolio-toolbar">
        <div class="portfolio-filters" role="group" aria-label="Filtrar itens por categoria">
          ${categorias.map((categoria) => `<button type="button" class="portfolio-filter ${portfolioFilter === categoria.id ? "active" : ""}" data-portfolio-filter="${escapeHTML(categoria.id)}" aria-pressed="${portfolioFilter === categoria.id}">${escapeHTML(categoria.name)} (${categoria.count})</button>`).join("")}
        </div>
        <button type="button" class="primary portfolio-add" id="portfolio-add" ${!portfolioLoaded || portfolioLoading ? "disabled" : ""}>+ Adicionar Item</button>
      </div>
      ${portfolioLoading && !portfolioLoaded ? '<div class="panel empty">Carregando itens do portfólio…</div>' : ""}
      ${portfolioError ? `<section class="panel portfolio-error" role="alert"><p>${escapeHTML(portfolioError)}</p><button type="button" class="primary" id="portfolio-retry">Tentar novamente</button></section>` : ""}
      <div class="portfolio-grid-admin">
        ${itensVisiveis
          .map(
            (portfolioItem) => `
          <article class="portfolio-card-admin">
            ${portfolioItem.image ? `<img src="${escapeHTML(portfolioItem.image)}" alt="${escapeHTML(portfolioItem.title)}" loading="lazy">` : '<div class="portfolio-image-empty" aria-hidden="true">▧</div>'}
            <div class="portfolio-card-body">
              <div class="portfolio-card-title-row"><button type="button" class="portfolio-edit" data-portfolio-edit="${escapeHTML(portfolioItem.id)}" aria-label="Editar item: ${escapeHTML(portfolioItem.title)}">${escapeHTML(portfolioItem.title)}</button><button type="button" class="portfolio-delete" data-portfolio-delete="${escapeHTML(portfolioItem.id)}" aria-label="Excluir ${escapeHTML(portfolioItem.title)}" title="Excluir item">×</button></div>
              <span class="portfolio-card-category">${escapeHTML(portfolioItem.category)}</span>
              <div class="portfolio-card-meta"><span>${portfolioItem.createdAt ? escapeHTML(dateBR(portfolioItem.createdAt)) : "Sem data"}</span>${portfolioItem.published ? "" : '<span class="portfolio-publication draft">Rascunho</span>'}</div>
            </div>
          </article>`,
          )
          .join("")}
        ${!portfolioLoading && !portfolioError && !itensVisiveis.length ? '<div class="panel empty portfolio-vazio">Nenhum item nesta categoria. Use “Adicionar Item” para incluir um serviço concluído.</div>' : ""}
      </div>
    </section>`;

  content.querySelectorAll("[data-portfolio-filter]").forEach((button) =>
    button.addEventListener("click", () => {
      portfolioFilter = button.dataset.portfolioFilter;
      renderPortfolio();
    }),
  );
  content
    .querySelector("#portfolio-add")
    .addEventListener("click", () => abrirPortfolioModal());
  content.querySelector("#portfolio-retry")?.addEventListener("click", () => {
    portfolioLoaded = false;
    portfolioError = "";
    void carregarPortfolioAdmin();
  });
  content.querySelectorAll("[data-portfolio-edit]").forEach((button) =>
    button.addEventListener("click", () => {
      const registro = portfolioItems.find(
        (registro) => registro.id === button.dataset.portfolioEdit,
      );
      if (registro) abrirPortfolioModal(registro);
    }),
  );
  content.querySelectorAll("[data-portfolio-delete]").forEach((button) =>
    button.addEventListener("click", async () => {
      const item = portfolioItems.find(
        (registro) => registro.id === button.dataset.portfolioDelete,
      );
      if (!item || !confirm(`Excluir “${item.title}” do portfólio?`)) return;
      const dados = new FormData();
      dados.set("acao", "remover");
      dados.set("csrf", adminCsrf);
      dados.set("id", item.id);
      button.disabled = true;
      try {
        const resultado = await enviarPortfolio(dados);
        adminMessage = resultado.mensagem || "Item removido.";
        adminMessageError = false;
        portfolioLoaded = false;
        await carregarPortfolioAdmin();
        if (page === "portfolio") render();
      } catch (erro) {
        adminMessage = erro.message || "Não foi possível remover o item.";
        adminMessageError = true;
        render();
      }
    }),
  );
}

function renderAdminNotifications() {
  const lista = document.querySelector("#admin-notification-list");
  const resumo = document.querySelector("#notifications-summary");
  const naoLidas = adminNotifications.filter((item) => !item.lida).length;
  document.querySelector("#notification-count").textContent = naoLidas;
  resumo.textContent = `${naoLidas} não lida(s)`;
  lista.innerHTML = adminNotifications.length
    ? adminNotifications.map((item) => `
        <button type="button" class="admin-notification-row ${item.lida ? "read" : "unread"}"
          data-admin-notification-id="${escapeHTML(item.id)}"
          data-admin-notification-protocol="${escapeHTML(item.protocolo || "")}">
          <span class="admin-notification-dot" aria-hidden="true"></span>
          <span><strong>${escapeHTML(item.titulo)}</strong><small>${escapeHTML(item.mensagem)}</small><time>${escapeHTML(dateBR(String(item.criada_em || "").slice(0, 10)))} ${escapeHTML(String(item.criada_em || "").slice(11, 16))}</time></span>
        </button>`).join("")
    : '<div class="empty">Você não tem notificações.</div>';
}

async function carregarNotificacoesAdmin() {
  try {
    const resposta = await fetch(ADMIN_NOTIFICATIONS_API, {
      credentials: "same-origin", cache: "no-store", headers: { Accept: "application/json" },
    });
    const dados = await resposta.json();
    if (resposta.status === 401 || resposta.status === 403) {
      window.location.replace("./login.html");
      return;
    }
    if (!resposta.ok || dados?.sucesso !== true || !Array.isArray(dados.notificacoes)) {
      throw new Error(dados?.mensagem || "Falha ao carregar notificações.");
    }
    adminNotifications = dados.notificacoes;
    notificationsCsrf = String(dados.csrf || adminCsrf);
    renderAdminNotifications();
  } catch (erro) {
    console.error("Falha ao carregar notificações administrativas.", erro);
  }
}

async function acaoNotificacaoAdmin(acao, id = "") {
  const feedback = document.querySelector("#admin-notification-feedback");
  feedback.hidden = true;
  try {
    const resposta = await fetch(ADMIN_NOTIFICATIONS_API, {
      method: "POST", credentials: "same-origin", cache: "no-store",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ acao, id, csrf: notificationsCsrf || adminCsrf }),
    });
    const dados = await resposta.json();
    if (resposta.status === 401 || resposta.status === 403) {
      window.location.replace("./login.html");
      return false;
    }
    if (!resposta.ok || dados?.sucesso !== true) throw new Error(dados?.mensagem || "Não foi possível atualizar as notificações.");
    feedback.textContent = dados.mensagem || "Notificações atualizadas.";
    feedback.hidden = false;
    await carregarNotificacoesAdmin();
    return true;
  } catch (erro) {
    feedback.textContent = erro.message || "Falha ao atualizar notificações.";
    feedback.hidden = false;
    return false;
  }
}

function render() {
  const pending = orders.filter(
    (order) => order.statusCode === "SOLICITADO",
  ).length;
  document.querySelector("#pending-count").textContent = pending;
  document.querySelector("#notification-count").textContent = adminNotifications.filter((item) => !item.lida).length;

  document.querySelectorAll(".tab").forEach((tab) => {
    const active = tab.dataset.page === page;
    tab.classList.toggle("active", active);
    if (active) tab.setAttribute("aria-current", "page");
    else tab.removeAttribute("aria-current");
  });

  if (adminLoading) {
    content.innerHTML =
      '<section class="panel"><div class="empty">Carregando solicitações do banco…</div></section>';
    return;
  }
  if (adminError) {
    content.innerHTML = `
      <section class="panel" role="status">
        <div class="empty">${escapeHTML(adminError)}</div>
        <button type="button" class="primary" id="retry-admin-load">Tentar novamente</button>
      </section>`;
    content
      .querySelector("#retry-admin-load")
      .addEventListener("click", () => carregarSolicitacoesAdmin());
    return;
  }

  if (page === "overview") renderOverview();
  if (page === "requests") renderRequests();
  if (page === "history") renderRequests(true);
  if (page === "schedule") renderSchedule();
  if (page === "portfolio") renderPortfolio();
  if (page === "technicians") renderTechnicians();

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
  if (nextPage === "technicians" && !techniciansLoaded) void carregarTecnicosAdmin();
}

document.querySelectorAll(".tab").forEach((tab) => {
  tab.addEventListener("click", () => navigate(tab.dataset.page));
});

document.querySelector("#notifications").addEventListener("click", () => {
  renderAdminNotifications();
  document.querySelector("#admin-notification-feedback").hidden = true;
  document.querySelector("#notifications-dialog").showModal();
  void carregarNotificacoesAdmin();
});

document.querySelector("#close-notifications").addEventListener("click", () => document.querySelector("#notifications-dialog").close());
document.querySelector("#mark-notifications-read").addEventListener("click", () => void acaoNotificacaoAdmin("ler_todas"));
document.querySelector("#clear-read-notifications").addEventListener("click", () => void acaoNotificacaoAdmin("limpar_lidas"));
document.querySelector("#admin-notification-list").addEventListener("click", async (event) => {
  const botao = event.target.closest("[data-admin-notification-id]");
  if (!botao) return;
  const protocolo = botao.dataset.adminNotificationProtocol || "";
  const marcado = await acaoNotificacaoAdmin("ler", botao.dataset.adminNotificationId);
  if (protocolo && marcado) {
    document.querySelector("#notifications-dialog").close();
    page = "requests";
    search = protocolo;
    filter = "Todos";
    render();
  }
});

document.querySelector("#close-technician")?.addEventListener("click", () => document.querySelector("#technician-dialog").close());
document.querySelector("#cancel-technician")?.addEventListener("click", () => document.querySelector("#technician-dialog").close());
document.querySelector("#technician-form")?.addEventListener("submit", async (event) => {
  event.preventDefault();
  const formTecnico = event.currentTarget;
  if (!formTecnico.reportValidity()) return;
  const botao = document.querySelector("#save-technician");
  const feedback = document.querySelector("#technician-feedback");
  botao.disabled = true;
  feedback.hidden = true;
  const id = String(formTecnico.elements.technician_id.value || "");
  try {
    const resposta = await fetch(TECH_API, {
      method: "POST", credentials: "same-origin", cache: "no-store",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({
        acao: id ? "atualizar" : "criar", id,
        nome: String(formTecnico.elements.name.value || "").trim(),
        email: String(formTecnico.elements.email.value || "").trim(),
        telefone: String(formTecnico.elements.phone.value || "").trim(),
        senha: String(formTecnico.elements.password.value || ""),
        csrf: technicianCsrf,
      }),
    });
    const dados = await resposta.json();
    if (resposta.status === 401 || resposta.status === 403) {
      window.location.replace("./login.html");
      return;
    }
    if (!resposta.ok || dados?.sucesso !== true) throw new Error(dados?.mensagem || "Não foi possível salvar o técnico.");
    document.querySelector("#technician-dialog").close();
    formTecnico.reset();
    adminMessage = dados.mensagem;
    adminMessageError = false;
    techniciansLoaded = false;
    await carregarTecnicosAdmin(false);
  } catch (erro) {
    feedback.textContent = erro.message || "Falha ao salvar técnico.";
    feedback.hidden = false;
  } finally {
    botao.disabled = false;
  }
});

document.querySelector("#new-order").addEventListener("click", () => {
  abrirModalNovoPedido();
});

document.querySelector("#cancel-order").addEventListener("click", () => {
  dialog.close();
});

document
  .querySelector("#close-order")
  .addEventListener("click", () => dialog.close());

function dataAposDias(dias) {
  const data = new Date();
  data.setDate(data.getDate() + dias);
  return [
    data.getFullYear(),
    String(data.getMonth() + 1).padStart(2, "0"),
    String(data.getDate()).padStart(2, "0"),
  ].join("-");
}

function preencherOpcoesPedido() {
  form.elements.equipment.innerHTML = [
    '<option value="">Selecione o serviço</option>',
    ...serviceTypes.map(
      (servico) =>
        `<option value="${escapeHTML(servico.id)}">${escapeHTML(servico.name)}</option>`,
    ),
  ].join("");
  form.elements.technician.innerHTML = [
    '<option value="">Não atribuído</option>',
    ...technicians.map(
      (tecnico) =>
        `<option value="${escapeHTML(tecnico.id)}">${escapeHTML(tecnico.name)}</option>`,
    ),
  ].join("");
}

let requisicaoHorariosPedido = 0;
async function carregarHorariosPedido() {
  const data = form.elements.date.value;
  const seletor = form.elements.time;
  const feedback = document.querySelector("#order-feedback");
  const requisicaoAtual = ++requisicaoHorariosPedido;
  seletor.disabled = true;
  document.querySelector("#submit-order").disabled = true;
  seletor.innerHTML = '<option value="">Carregando horários...</option>';
  feedback.hidden = true;
  feedback.textContent = "";
  if (!data) {
    seletor.innerHTML = '<option value="">Selecione uma data</option>';
    return;
  }

  const [ano, mes] = data.split("-").map(Number);
  try {
    const resposta = await fetch(`${AGENDA_API}?ano=${ano}&mes=${mes}`, {
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" },
    });
    const dados = await resposta.json();
    if (resposta.status === 401 || resposta.status === 403) {
      window.location.replace("./login.html");
      return;
    }
    if (
      !resposta.ok ||
      dados?.sucesso !== true ||
      !Array.isArray(dados.horarios)
    ) {
      throw new Error(
        dados?.mensagem || "Não foi possível carregar os horários.",
      );
    }
    if (typeof dados.csrf === "string") adminCsrf = dados.csrf;
    if (requisicaoAtual !== requisicaoHorariosPedido) return;
    const agora = new Date();
    const disponiveis = dados.horarios.filter((horario) => {
      if (
        horario.estado !== "free" ||
        String(horario.inicio).slice(0, 10) !== data
      )
        return false;
      const inicio = new Date(String(horario.inicio).replace(" ", "T"));
      return !Number.isNaN(inicio.getTime()) && inicio > agora;
    });
    seletor.innerHTML = [
      `<option value="">${disponiveis.length ? "Selecione o horário" : "Nenhum horário livre nesta data"}</option>`,
      ...disponiveis.map(
        (horario) =>
          `<option value="${escapeHTML(horario.id)}">${escapeHTML(String(horario.inicio).slice(11, 16))}</option>`,
      ),
    ].join("");
    seletor.disabled = disponiveis.length === 0;
  } catch (erro) {
    if (requisicaoAtual !== requisicaoHorariosPedido) return;
    seletor.innerHTML = '<option value="">Erro ao carregar horários</option>';
    feedback.textContent =
      erro.message || "Não foi possível carregar os horários.";
    feedback.hidden = false;
    document.querySelector("#submit-order").disabled = true;
  }
}

let buscaClienteSequencia = 0;
let buscaClienteTemporizador;
let buscaClientePendente = false;
let buscaClienteFalhou = false;

function resetarBuscaCliente() {
  buscaClienteSequencia += 1;
  clearTimeout(buscaClienteTemporizador);
  buscaClientePendente = false;
  buscaClienteFalhou = false;
  const seletor = form.elements.namedItem("client_id");
  const feedback = document.querySelector("#client-match-feedback");
  if (!seletor || !feedback) return;
  seletor.replaceChildren(new Option("Informe o telefone para buscar contas", ""));
  seletor.disabled = true;
  feedback.textContent = "O pedido aparecerá no painel do cliente se for vinculado a uma conta.";
}

form.elements.phone.addEventListener("input", () => {
  const seletor = form.elements.namedItem("client_id");
  const feedback = document.querySelector("#client-match-feedback");
  if (!seletor || !feedback) return;
  const telefone = form.elements.phone.value.replace(/\D/g, "");
  const sequencia = ++buscaClienteSequencia;
  clearTimeout(buscaClienteTemporizador);
  buscaClientePendente = false;
  buscaClienteFalhou = false;
  seletor.replaceChildren(new Option("Buscando conta…", ""));
  seletor.disabled = true;
  if (!/^\d{10,11}$/.test(telefone)) {
    seletor.replaceChildren(new Option("Informe telefone com DDD", ""));
    feedback.textContent = "Digite um telefone válido com DDD para procurar uma conta.";
    return;
  }
  buscaClientePendente = true;
  feedback.textContent = "Procurando contas cadastradas com este telefone…";
  buscaClienteTemporizador = setTimeout(async () => {
    try {
      const resposta = await fetch(`${API}?acao=buscar_cliente&telefone=${encodeURIComponent(telefone)}`, {
        credentials: "same-origin", cache: "no-store", headers: { Accept: "application/json" },
      });
      const resultado = await resposta.json();
      if (sequencia !== buscaClienteSequencia) return;
      if (resposta.status === 401 || resposta.status === 403) {
        window.location.replace("./login.html");
        return;
      }
      if (!resposta.ok || resultado?.sucesso !== true) {
        throw new Error(resultado?.mensagem || "Não foi possível buscar a conta.");
      }
      buscaClientePendente = false;
      const clientes = Array.isArray(resultado.clientes) ? resultado.clientes : [];
      seletor.replaceChildren(new Option("Não vincular a uma conta", ""));
      clientes.forEach((cliente) => {
        const opcao = new Option(`${cliente.nome} — ${cliente.email}`, String(cliente.id));
        opcao.dataset.nome = String(cliente.nome || "");
        opcao.dataset.telefone = String(cliente.telefone || "");
        seletor.add(opcao);
      });
      seletor.disabled = clientes.length === 0;
      feedback.textContent = clientes.length
        ? `${clientes.length} conta(s) encontrada(s). Selecione a conta correta para vincular.`
        : "Nenhuma conta encontrada. O pedido será salvo sem vínculo com uma conta.";
    } catch (erro) {
      if (sequencia !== buscaClienteSequencia) return;
      buscaClientePendente = false;
      buscaClienteFalhou = true;
      seletor.replaceChildren(new Option("Busca indisponível", ""));
      seletor.disabled = true;
      feedback.textContent = erro.message || "Falha ao buscar conta do cliente.";
    }
  }, 300);
});

form.elements.namedItem("client_id")?.addEventListener("change", (event) => {
  const opcao = event.currentTarget.selectedOptions[0];
  if (!opcao?.value) return;
  form.elements.client.value = opcao.dataset.nome || form.elements.client.value;
  form.elements.phone.value = opcao.dataset.telefone || form.elements.phone.value;
  const feedback = document.querySelector("#client-match-feedback");
  if (feedback) feedback.textContent = "Conta selecionada. O pedido aparecerá no painel desse cliente.";
});

function abrirModalNovoPedido() {
  if (adminLoading || !adminCsrf) {
    adminMessage = adminLoading
      ? "Aguarde o carregamento dos dados do painel para cadastrar um pedido."
      : "Não foi possível carregar os dados necessários para cadastrar um pedido.";
    adminMessageError = true;
    render();
    return;
  }
  form.reset();
  resetarBuscaCliente();
  preencherOpcoesPedido();
  form.elements.date.min = TODAY;
  form.elements.date.max = dataAposDias(30);
  form.elements.date.value = TODAY;
  form.elements.time.disabled = true;
  form.elements.time.innerHTML =
    '<option value="">Carregando horários...</option>';
  const feedback = document.querySelector("#order-feedback");
  feedback.textContent = "";
  feedback.hidden = true;
  const botaoAdicionar = document.querySelector("#submit-order");
  botaoAdicionar.disabled = true;
  if (serviceTypes.length === 0) {
    feedback.textContent =
      "Não há tipos de serviço ativos cadastrados no sistema.";
    feedback.hidden = false;
  }
  dialog.showModal();
  void carregarHorariosPedido();
}

form.elements.date.addEventListener(
  "change",
  () => void carregarHorariosPedido(),
);
form.elements.time.addEventListener("change", () => {
  document.querySelector("#submit-order").disabled =
    !form.elements.time.value || serviceTypes.length === 0;
});

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (buscaClientePendente) {
    document.querySelector("#client-match-feedback").textContent =
      "Aguarde a busca da conta terminar antes de cadastrar o pedido.";
    return;
  }
  if (buscaClienteFalhou) {
    document.querySelector("#client-match-feedback").textContent =
      "A busca falhou. Altere o telefone e tente novamente antes de salvar.";
    return;
  }
  if (!form.reportValidity()) return;
  const botao = document.querySelector("#submit-order");
  const feedback = document.querySelector("#order-feedback");
  botao.disabled = true;
  feedback.hidden = true;
  const dadosFormulario = new FormData(form);
  const pedido = {
    acao: "criar",
    csrf: adminCsrf,
    nome: String(dadosFormulario.get("client") || "").trim(),
    telefone: String(dadosFormulario.get("phone") || "").trim(),
    id_cliente: String(dadosFormulario.get("client_id") || ""),
    id_tipo_servico: String(dadosFormulario.get("equipment") || ""),
    id_disponibilidade: String(dadosFormulario.get("time") || ""),
    endereco: String(dadosFormulario.get("address") || "").trim(),
    descricao: String(dadosFormulario.get("description") || "").trim(),
    id_tecnico: String(dadosFormulario.get("technician") || ""),
    prioridade: String(dadosFormulario.get("priority") || "normal"),
  };
  try {
    const resposta = await fetch(API, {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(pedido),
    });
    const resultado = await resposta.json();
    if (resposta.status === 401 || resposta.status === 403) {
      window.location.replace("./login.html");
      return;
    }
    if (!resposta.ok || resultado?.sucesso !== true) {
      throw new Error(
        resultado?.mensagem || "Não foi possível cadastrar o pedido.",
      );
    }
    dialog.close();
    form.reset();
    adminMessage = `${resultado.mensagem || "Pedido cadastrado."} Protocolo: ${resultado.protocolo}.`;
    adminMessageError = false;
    await carregarSolicitacoesAdmin(false);
    render();
  } catch (erro) {
    feedback.textContent =
      erro.message || "Não foi possível cadastrar o pedido.";
    feedback.hidden = false;
  } finally {
    botao.disabled =
      !form.elements.time.value ||
      form.elements.time.disabled ||
      serviceTypes.length === 0;
  }
});

form.elements.client.addEventListener("input", () => {
  form.elements.client.setCustomValidity("");
});

// A saída é enviada por POST ao PHP para encerrar a sessão.

render();
void carregarSolicitacoesAdmin();
