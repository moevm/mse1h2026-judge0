const langMap = { "Python": "python", "C++": "cpp", "JS": "javascript" };

function switchLang(btn) {
    const card = btn.parentElement.parentElement;
    card.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('bg-emerald-600', 'text-white'));
    btn.classList.add('bg-emerald-600', 'text-white');
}

async function runTask(btn) {
    const card = btn.parentElement;
    const task = card.dataset.task;
    const activeBtn = card.querySelector('.lang-btn.bg-emerald-600');
    const lang = langMap[activeBtn.textContent.trim()];
    const code = card.querySelector('.code').value.trim();

    if (!code) return alert("Напишите код!");

    const res = await fetch("/submit", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ task, language: lang, source_code: code })
    });

    const data = await res.json();
    const resultDiv = card.querySelector('.result');
    resultDiv.classList.remove('hidden');

    let html = `
        <div class="text-2xl font-bold mb-4 ${data.passed === data.total_tests_run ? 'text-emerald-400' : 'text-red-400'}">
            ${data.passed} / ${data.total_tests_run} пройдено
        </div>
        <table class="w-full text-sm">
            <thead><tr class="border-b border-zinc-700"><th class="py-2 text-left">Тест</th><th class="py-2 text-left">Вердикт</th><th class="py-2 text-left">Время</th><th class="py-2 text-left">Детали</th></tr></thead>
            <tbody>`;

    data.results.forEach(r => {
        let detail = r.judge0_status || '';
        if (r.compile_output) detail += `<br><span class="text-red-400">compile: ${r.compile_output}</span>`;
        if (r.stderr) detail += `<br><span class="text-red-400">stderr: ${r.stderr}</span>`;

        html += `
            <tr class="border-b border-zinc-800">
                <td class="py-3">${r.test}</td>
                <td class="py-3 ${r.verdict==='Accepted'?'text-emerald-400':'text-red-400'}">${r.verdict}</td>
                <td class="py-3">${r.time||'-'}s</td>
                <td class="py-3 text-xs text-zinc-400">${detail}</td>
            </tr>`;
    });

    html += `</tbody></table>`;
    resultDiv.innerHTML = html;
}

async function loadDescriptions() {
    const cards = document.querySelectorAll('[data-task]');
    for (const card of cards) {
        const task = card.dataset.task;
        const res = await fetch(`/tasks/${task}`);
        const data = await res.json();
        card.querySelector('p').textContent = data.description;
    }
}

loadDescriptions();