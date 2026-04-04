(function () {
  class TotalfilterChatWidget extends HTMLElement {
    constructor() {
      super();
      this.attachShadow({ mode: "open" });
      this.state = {
        open: false,
        loading: false,
        leadOpen: false,
        pendingHumanConfirm: false,
        contextActions: [],
        config: null,
        sessionToken: "",
        visitorId: localStorage.getItem("tf_chat_visitor_id") || this.uuid(),
        messages: [],
      };
      localStorage.setItem("tf_chat_visitor_id", this.state.visitorId);
      this.uiDelay = 1200;
    }

    connectedCallback() {
      this.baseUrl = this.getAttribute("base-url") || "";
      this.apiBaseUrl = this.getAttribute("api-base-url") || this.baseUrl;
      this.renderShell();
      this.loadConfig().then(() => this.bootstrapSession());
    }

    async loadConfig() {
      const response = await fetch(`${this.apiBaseUrl}/api/config`);
      const payload = await response.json();
      this.state.config = payload.assistant;
      this.render();
    }

    async bootstrapSession() {
      const sessionToken = localStorage.getItem("tf_chat_session_token");
      if (sessionToken) {
        this.state.sessionToken = sessionToken;
        const restored = await this.fetchHistory(sessionToken);
        if (restored) {
          this.state.messages = restored.history || [];
          this.renderMessages();
          return;
        }
      }

      const response = await fetch(`${this.apiBaseUrl}/api/chat/start`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          visitor_id: this.state.visitorId,
          page_url: window.location.href,
          referrer_url: document.referrer || "",
          locale: document.documentElement.lang || "pt-BR",
        }),
      });
      const payload = await response.json();
      this.state.sessionToken = payload.session_token;
      this.state.messages = payload.history || [];
      this.state.contextActions = payload.context_actions || [];
      localStorage.setItem("tf_chat_session_token", payload.session_token);
      this.renderMessages();
    }

    async fetchHistory(sessionToken) {
      try {
        const response = await fetch(`${this.apiBaseUrl}/api/chat/history?session_token=${encodeURIComponent(sessionToken)}`);
        if (!response.ok) return null;
        return await response.json();
      } catch (error) {
        return null;
      }
    }

    renderShell() {
      this.shadowRoot.innerHTML = `
        <link rel="stylesheet" href="${this.baseUrl}/chat-widget/widget.css">
        <div class="tf-chat-root" data-open="false" data-lead-open="false">
          <div class="tf-chat-panel" role="dialog" aria-label="Assistente digital Totalfilter">
            <header class="tf-chat-header">
              <div class="tf-chat-avatar"><img alt="Mascote Totalfilter"></div>
              <div>
                <h2 class="tf-chat-title"></h2>
                <p class="tf-chat-subtitle"></p>
              </div>
              <div class="tf-chat-actions">
                <button type="button" class="tf-reset" aria-label="Reiniciar conversa">&#8634;</button>
              </div>
            </header>
            <div class="tf-chat-body" tabindex="0" aria-live="polite"></div>
            <div class="tf-chat-context-actions" aria-label="Acoes rapidas do contexto"></div>
            <div class="tf-chat-quick" aria-label="Sugestoes rapidas"></div>
            <footer class="tf-chat-footer">
              <form class="tf-chat-form">
                <textarea class="tf-chat-input" aria-label="Mensagem" rows="1"></textarea>
                <button class="tf-chat-submit" aria-label="Enviar mensagem">&#8594;</button>
              </form>
              <div class="tf-chat-meta">
                <div class="tf-status"></div>
                <button type="button" class="tf-ghost tf-human">Atendimento humano</button>
              </div>
            </footer>
          </div>
          <button type="button" class="tf-chat-launcher" aria-label="Abrir chat Totalfilter">
            <img alt="Mascote Totalfilter">
          </button>
          <div class="tf-lead-sheet" aria-hidden="true">
            <div class="tf-lead-card">
              <h3>Receber retorno comercial</h3>
              <p>Preencha os dados para a equipe Totalfilter continuar o atendimento.</p>
              <form class="tf-lead-form">
                <div class="tf-lead-grid">
                  <input name="name" placeholder="Nome" required>
                  <input name="phone" placeholder="Telefone" required>
                  <input name="email" placeholder="E-mail">
                  <input name="company" placeholder="Empresa">
                  <input class="full" name="city_state" placeholder="Cidade/Estado">
                  <input class="full" name="product_interest" placeholder="Produto ou aplicacao de interesse">
                  <textarea class="full" name="message" rows="3" placeholder="Mensagem"></textarea>
                </div>
                <div class="tf-lead-actions">
                  <button type="button" class="tf-ghost tf-lead-cancel">Cancelar</button>
                  <button type="submit" class="tf-chat-submit">Enviar</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      `;

      this.rootEl = this.shadowRoot.querySelector(".tf-chat-root");
      this.bodyEl = this.shadowRoot.querySelector(".tf-chat-body");
      this.contextActionsEl = this.shadowRoot.querySelector(".tf-chat-context-actions");
      this.quickEl = this.shadowRoot.querySelector(".tf-chat-quick");
      this.statusEl = this.shadowRoot.querySelector(".tf-status");
      this.inputEl = this.shadowRoot.querySelector(".tf-chat-input");

      this.shadowRoot.querySelector(".tf-chat-launcher").addEventListener("click", () => {
        this.state.open = !this.state.open;
        this.render();
      });

      this.shadowRoot.querySelector(".tf-chat-form").addEventListener("submit", (event) => {
        event.preventDefault();
        this.sendMessage(this.inputEl.value);
      });

      this.shadowRoot.querySelector(".tf-reset").addEventListener("click", () => this.resetConversation());
      this.shadowRoot.querySelector(".tf-human").addEventListener("click", () => this.handleHumanSupport());
      this.shadowRoot.querySelector(".tf-lead-cancel").addEventListener("click", () => this.toggleLead(false));
      this.shadowRoot.querySelector(".tf-lead-form").addEventListener("submit", (event) => this.submitLead(event));
    }

    render() {
      const config = this.state.config || {
        title: "Assistente Totalfilter",
        subtitle: "Atendimento digital",
        placeholder: "Escreva sua mensagem",
        quick_replies: [],
        mascot_url: `${this.baseUrl}/chat-widget/assets/mascot.svg`,
        primary_color: "#0A0A0A",
        accent_color: "#FFD100",
      };

      this.rootEl.dataset.open = String(this.state.open);
      this.rootEl.dataset.leadOpen = String(this.state.leadOpen);
      this.rootEl.style.setProperty("--tf-primary", config.primary_color);
      this.rootEl.style.setProperty("--tf-accent", config.accent_color);

      this.shadowRoot.querySelector(".tf-chat-title").textContent = config.title;
      this.shadowRoot.querySelector(".tf-chat-subtitle").textContent = config.subtitle;
      this.inputEl.placeholder = config.placeholder;
      this.shadowRoot.querySelectorAll("img").forEach((img) => {
        img.src = config.mascot_url;
      });

      this.quickEl.innerHTML = "";
      (config.quick_replies || []).forEach((label) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "tf-chip";
        button.textContent = label;
        button.addEventListener("click", () => this.sendMessage(label));
        this.quickEl.appendChild(button);
      });

      this.renderMessages();
    }

    renderMessages() {
      if (!this.bodyEl) return;
      this.bodyEl.innerHTML = "";

      this.state.messages.forEach((message) => {
        const item = document.createElement("div");
        item.className = `tf-msg ${message.role === "user" ? "tf-msg-user" : "tf-msg-assistant"}`;
        item.appendChild(this.renderMessageContent(message.content || ""));
        const meta = this.parseMeta(message.meta_json);
        if (meta.product_cards && meta.product_cards.length) {
          item.appendChild(this.renderProductCards(meta.product_cards));
        }
        this.bodyEl.appendChild(item);
      });

      this.bodyEl.scrollTop = this.bodyEl.scrollHeight;
      this.renderContextActions();
    }

    renderMessageContent(content) {
      const wrapper = document.createElement("div");
      wrapper.className = "tf-msg-content";
      const blocks = String(content).split(/\n\s*\n/).filter(Boolean);

      blocks.forEach((block) => {
        const lines = block.split("\n").map((line) => line.trim()).filter(Boolean);
        const summaryLines = lines.filter((line) => /^[^:]{2,40}:\s.+$/.test(line));

        if (summaryLines.length >= 3) {
          const summary = document.createElement("div");
          summary.className = "tf-msg-summary";

          lines.forEach((line) => {
            const match = line.match(/^([^:]{2,40}):\s(.+)$/);
            if (!match) {
              const paragraph = document.createElement("p");
              paragraph.className = "tf-msg-paragraph";
              paragraph.textContent = line;
              wrapper.appendChild(paragraph);
              return;
            }

            const row = document.createElement("div");
            row.className = "tf-msg-row";

            const label = document.createElement("div");
            label.className = "tf-msg-label";
            label.textContent = match[1];

            const value = document.createElement("div");
            value.className = "tf-msg-value";
            value.textContent = match[2];

            row.appendChild(label);
            row.appendChild(value);
            summary.appendChild(row);
          });

          wrapper.appendChild(summary);
          return;
        }

        const paragraph = document.createElement("p");
        paragraph.className = "tf-msg-paragraph";
        paragraph.textContent = lines.join("\n");
        wrapper.appendChild(paragraph);
      });

      return wrapper;
    }

    renderProductCards(cards) {
      const wrapper = document.createElement("div");
      wrapper.className = "tf-product-cards";

      cards.forEach((card) => {
        const article = document.createElement("div");
        article.className = "tf-product-card";

        const head = document.createElement("div");
        head.className = "tf-product-head";

        const titleWrap = document.createElement("div");
        const title = document.createElement("div");
        title.className = "tf-product-title";
        title.textContent = card.title || "Produto";
        titleWrap.appendChild(title);

        if (card.code) {
          const code = document.createElement("div");
          code.className = "tf-product-code";
          code.textContent = card.code;
          titleWrap.appendChild(code);
        }

        head.appendChild(titleWrap);

        if (card.status) {
          const status = document.createElement("div");
          status.className = "tf-product-status";
          status.textContent = card.status;
          head.appendChild(status);
        }

        article.appendChild(head);

        if (card.category) {
          const category = document.createElement("div");
          category.className = "tf-product-code";
          category.textContent = card.category;
          article.appendChild(category);
        }

        if (card.summary) {
          const summary = document.createElement("div");
          summary.className = "tf-product-summary";
          summary.textContent = card.summary;
          article.appendChild(summary);
        }

        if (card.details_url) {
          const link = document.createElement("a");
          link.className = "tf-product-link";
          link.href = card.details_url;
          link.target = "_blank";
          link.rel = "noopener";
          link.textContent = "Ver detalhes";
          article.appendChild(link);
        }

        wrapper.appendChild(article);
      });

      return wrapper;
    }

    parseMeta(metaJson) {
      if (!metaJson) return {};
      if (typeof metaJson === "object") return metaJson;
      try {
        return JSON.parse(metaJson);
      } catch (error) {
        return {};
      }
    }

    renderContextActions() {
      if (!this.contextActionsEl) return;
      this.contextActionsEl.innerHTML = "";

      const actions = [...(this.state.contextActions || [])];

      if (this.state.pendingHumanConfirm) {
        actions.splice(0, actions.length,
          { label: "Abrir WhatsApp", value: "sim" },
          { label: "Continuar no chat", value: "não" }
        );
      }

      actions.forEach((action) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "tf-chip";
        button.textContent = action.label;
        button.addEventListener("click", () => this.sendMessage(action.value));
        this.contextActionsEl.appendChild(button);
      });
    }

    async sendMessage(value) {
      const message = (value || "").trim();
      if (!message || this.state.loading) return;

      if (this.state.pendingHumanConfirm) {
        this.handleHumanConfirmation(message);
        return;
      }

      this.inputEl.value = "";
      this.state.loading = true;
      this.statusEl.textContent = "Totalfilter";
      this.statusEl.classList.add("tf-typing");
      this.state.messages.push({ role: "user", content: message });
      this.renderMessages();

      try {
        const response = await fetch(`${this.apiBaseUrl}/api/chat/message`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            session_token: this.state.sessionToken,
            message,
          }),
        });
        const payload = await response.json();
        this.state.messages = payload.history || this.state.messages;
        this.state.contextActions = payload.context_actions || [];
        this.renderMessages();
        if (payload.suggest_capture_lead) {
          setTimeout(() => this.toggleLead(true), this.uiDelay);
        }
      } catch (error) {
        this.state.messages.push({
          role: "assistant",
          content: "Houve uma instabilidade no envio. Voce pode tentar novamente ou pedir atendimento humano.",
        });
        this.renderMessages();
      } finally {
        this.state.loading = false;
        this.statusEl.textContent = "";
        this.statusEl.classList.remove("tf-typing");
      }
    }

    async resetConversation() {
      await fetch(`${this.apiBaseUrl}/api/chat/reset`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ session_token: this.state.sessionToken }),
      });
      localStorage.removeItem("tf_chat_session_token");
      this.state.messages = [];
      this.state.sessionToken = "";
      this.state.pendingHumanConfirm = false;
      this.state.contextActions = [];
      this.bootstrapSession();
    }

    toggleLead(open) {
      this.state.leadOpen = open;
      this.render();
    }

    async submitLead(event) {
      event.preventDefault();
      const form = event.currentTarget;
      const data = Object.fromEntries(new FormData(form).entries());
      data.session_token = this.state.sessionToken;
      data.source = "chat-widget";

      const response = await fetch(`${this.apiBaseUrl}/api/lead`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });
      const payload = await response.json();
      this.state.messages.push({ role: "assistant", content: payload.message || "Seus dados foram enviados com sucesso." });
      this.renderMessages();
      form.reset();
      this.toggleLead(false);
    }

    handleHumanSupport() {
      this.state.messages.push({
        role: "assistant",
        content: 'Posso te direcionar para o atendimento humano no WhatsApp da Totalfilter. Se quiser continuar, responda "sim". Se preferir ficar no chat, responda "não".',
      });
      this.state.pendingHumanConfirm = true;
      this.renderMessages();
    }

    openHumanSupport() {
      const phone = "5511974238992";
      const text = encodeURIComponent("Olá! Vim pelo site da Totalfilter e gostaria de falar com um atendente.");
      window.open(`https://wa.me/${phone}?text=${text}`, "_blank", "noopener");
    }

    handleHumanConfirmation(value) {
      const message = (value || "").trim();
      const normalized = message.toLowerCase();
      this.inputEl.value = "";
      this.state.messages.push({ role: "user", content: message });

      if (["sim", "s", "ok", "claro", "pode", "quero"].includes(normalized)) {
        this.state.messages.push({
          role: "assistant",
          content: "Perfeito. Vou abrir o WhatsApp da Totalfilter para voce agora.",
        });
        this.state.pendingHumanConfirm = false;
        this.renderMessages();
        setTimeout(() => this.openHumanSupport(), this.uiDelay);
        return;
      }

      if (["nao", "não", "n", "agora nao", "agora não"].includes(normalized)) {
        this.state.messages.push({
          role: "assistant",
          content: "Sem problema. Posso continuar te ajudando por aqui com produtos, orcamento, contatos ou outras duvidas.",
        });
        this.state.pendingHumanConfirm = false;
        this.renderMessages();
        return;
      }

      this.state.messages.push({
        role: "assistant",
        content: 'Se quiser abrir o WhatsApp da Totalfilter, responda "sim". Se preferir continuar no chat, responda "não".',
      });
      this.renderMessages();
    }

    uuid() {
      return "visitor-" + Math.random().toString(16).slice(2) + Date.now().toString(16);
    }
  }

  customElements.define("totalfilter-chat-widget", TotalfilterChatWidget);
})();
