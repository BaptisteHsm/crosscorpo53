/* Cross Corpo 53 – administration : lecture des PDF « Scratch » et des GPX dans le navigateur,
   puis envoi des seuls classements / coordonnées à WordPress. */
(function ($) {
	"use strict";
	const A = window.CC53_ADMIN || {};
	let core = null;
	const loadCore = () => core || (core = import(A.core));
	if (window.pdfjsLib) window.pdfjsLib.GlobalWorkerOptions.workerSrc = A.worker;
	// Le titre est généré automatiquement (« Cross N°3 – Saint-Berthevin »).
	$(function () { $("#title").prop("readonly", true).attr("placeholder", "Titre généré à l'enregistrement"); $("#title-prompt-text").text("Titre généré à l'enregistrement"); });

	const fmt = (n) => Number(n).toLocaleString("fr-FR");
	const send = (fields) =>
		$.post(A.ajax, Object.assign({ action: "cc53_save", nonce: A.nonce }, fields)).then((r) => {
			if (!r || !r.success) throw new Error((r && r.data && r.data.message) || "Erreur d'enregistrement");
			return r.data;
		}, (x) => { throw new Error((x.responseJSON && x.responseJSON.data && x.responseJSON.data.message) || "Erreur réseau"); });

	/* ---------- PDF « Scratch » ---------- */
	async function readPdf(file, box) {
		const C = await loadCore();
		const prev = box.find(".cc53-preview").prop("hidden", false).html("<p>Lecture du PDF…</p>");
		try {
			const buf = new Uint8Array(await file.arrayBuffer());
			const p = C.parseLines(await C.pdfLines(window.pdfjsLib, buf));
			const dists = Object.keys(p.dists);
			if (!dists.length) throw new Error("Aucun classement général reconnu dans ce PDF.");
			const out = { n: p.n, date: p.date, dists: {} };
			let html = "<ul>";
			for (const d of ["long", "court"].filter((k) => p.dists[k])) {
				const D = p.dists[d];
				const teams = D.teams.length ? D.teams : C.computeTeams(D.rows, d);
				// Minimisation : l'année de naissance devient une tranche d'âge avant tout envoi.
				const rows = D.rows.map(C.packRow).map((r) => { r[4] = C.ageBand(r[4]); return r; });
				out.dists[d] = { km: D.km, rows, teams: teams.map(C.packTeam) };
				html += `<li><strong>${d === "long" ? "Cross long" : "Cross court"}</strong> (${String(D.km).replace(".", ",")} km) : ${fmt(rows.length)} arrivants, ${fmt(teams.length)} équipes${teams.length && !D.teams.length ? " (calculées)" : ""}</li>`;
			}
			html += "</ul>";
			if (p.warnings && p.warnings.length) html += `<p>⚠ Ligne(s) mal formée(s) dans le PDF, reprises au mieux : ${p.warnings.map((w) => $("<i>").text(w).html()).join(", ")}.</p>`;
			const num = Number(A.num) || 0;
			const mismatch = p.n && num && p.n !== num;
			if (mismatch) html += `<p class="cc53-warn">⚠ Ce PDF est celui du <strong>cross N°${p.n}</strong>, alors que cette fiche est celle du cross N°${num}. Vérifiez le fichier avant d'enregistrer.</p>`;
			html += `<p><button type="button" class="button button-primary cc53-confirm">Enregistrer ces résultats</button> <button type="button" class="button-link cc53-cancel">Annuler</button></p>`;
			prev.html(html);
			prev.find(".cc53-cancel").on("click", () => prev.prop("hidden", true).empty());
			prev.find(".cc53-confirm").on("click", async function () {
				$(this).prop("disabled", true).text("Enregistrement…");
				try {
					const r = await send({ what: "results", post: box.data("post"), data: JSON.stringify(out) });
					prev.html(`<p class="cc53-ok">✅ Résultats enregistrés et en ligne${r.masked ? ` (${r.masked} coureur(s) masqué(s))` : ""}.</p>`);
					box.find(".cc53-state").html(dists.map((d) => `<span class="cc53-ok">✅ ${d === "long" ? "Cross long" : "Cross court"} : ${fmt(out.dists[d].rows.length)} arrivants</span>`).join(" "));
				} catch (e) { prev.append(`<p class="cc53-warn">${$("<i>").text(e.message).html()}</p>`); $(this).prop("disabled", false).text("Réessayer"); }
			});
		} catch (e) {
			prev.html(`<p class="cc53-warn">✗ ${$("<i>").text(e.message).html()}</p>`);
		}
	}

	/* ---------- GPX ---------- */
	async function readGpx(file, box) {
		const C = await loadCore();
		const st = box.find(".cc53-state").text("Lecture…");
		try {
			const s = await file.text();
			const P = C.gpxPoints(s);
			if (P.length < 10) throw new Error("Aucun tracé dans ce GPX.");
			const d = box.data("dist");
			const data = { pts: C.simplify(P) };
			if (d === "long") { const lap = C.firstLap(P); if (lap) data.lap = C.simplify(lap); }
			await send({ what: "track", dist: d, post: box.data("post"), data: JSON.stringify(data) });
			st.html(`✅ ${(C.trackLength(P) / 1000).toFixed(2).replace(".", ",")} km enregistrés${data.lap ? " (le cross court reprend le premier tour, sauf GPX court déposé)" : ""}`);
		} catch (e) { st.html(`<span class="cc53-warn">✗ ${$("<i>").text(e.message).html()}</span>`); }
	}

	$(document).on("change", ".cc53-drop input[type=file]", function () {
		const f = this.files && this.files[0];
		const box = $(this).closest(".cc53-drop");
		this.value = "";
		if (!f) return;
		(box.data("kind") === "pdf" ? readPdf : readGpx)(f, box);
	});

	$(document).on("click", ".cc53-del", async function () {
		const box = $(this).closest(".cc53-drop");
		const res = $(this).data("what") === "results";
		if (!window.confirm(res ? "Supprimer les résultats de ce cross ?" : "Supprimer ce tracé ?")) return;
		try {
			await send({ what: res ? "del_results" : "del_track", dist: box.data("dist") || "", post: box.data("post"), data: "{}" });
			box.find(".cc53-state").text(res ? "Résultats supprimés." : "Tracé supprimé.");
			$(this).remove();
		} catch (e) { window.alert(e.message); }
	});

	/* ---------- Lignes répétables (parkings, organisateurs) ---------- */
	$(document).on("click", ".cc53-add", function () {
		const rows = $(this).prev(".cc53-rows");
		const last = rows.children(".cc53-row").last();
		const clone = last.clone();
		const i = rows.children(".cc53-row").length;
		clone.find("input").each(function () { this.value = ""; this.name = this.name.replace(/\[\d+\]/, `[${i}]`); });
		clone.find(".cc53-logo").empty();
		rows.append(clone);
	});
	$(document).on("click", ".cc53-rm", function () {
		const row = $(this).closest(".cc53-row");
		if (row.siblings(".cc53-row").length) row.remove();
		else row.find("input").val("").end().find(".cc53-logo").empty();
	});
	$(document).on("click", ".cc53-media", function (e) {
		e.preventDefault();
		const row = $(this).closest(".cc53-row");
		const frame = wp.media({ title: "Logo de l'organisateur", library: { type: "image" }, multiple: false, button: { text: "Utiliser ce logo" } });
		frame.on("select", () => {
			const a = frame.state().get("selection").first().toJSON();
			row.find(".cc53-logo-id").val(a.id);
			const src = (a.sizes && a.sizes.thumbnail && a.sizes.thumbnail.url) || a.url;
			row.find(".cc53-logo").html($("<img alt=''>").attr("src", src));
			const name = row.find("input[type=text]");
			if (!name.val()) name.val(a.title || "");
		});
		frame.open();
	});
})(jQuery);
