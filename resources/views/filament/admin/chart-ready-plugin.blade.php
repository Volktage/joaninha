{{--
    Plugin global do Chart.js, carregado antes do módulo de gráficos do Filament:

    1. Depois do primeiro desenho, marca o contêiner com data-chart-ready (o tema usa isso para
       esconder o brilho de carregamento).
    2. Garante que o gráfico apareça mesmo se o primeiro desenho se perder (canvas limpo por um
       redimensionamento, camada do navegador não repintada etc.): redesenha nos próximos quadros,
       algumas vezes logo após o carregamento e sempre que o gráfico entra na área visível.
--}}
<script>
    (() => {
        const redraw = (chart) => {
            if (! chart?.canvas?.isConnected) {
                return;
            }

            chart.resize();
            chart.update('none');
        };

        window.filamentChartJsGlobalPlugins = [
            ...(window.filamentChartJsGlobalPlugins ?? []),
            {
                id: 'joaninhaChartReady',

                afterInit(chart) {
                    requestAnimationFrame(() => requestAnimationFrame(() => redraw(chart)));

                    for (const delay of [250, 750, 1500]) {
                        setTimeout(() => redraw(chart), delay);
                    }

                    if ('IntersectionObserver' in window) {
                        chart.$joaninhaObserver = new IntersectionObserver((entries) => {
                            if (entries.some((entry) => entry.isIntersecting)) {
                                requestAnimationFrame(() => redraw(chart));
                            }
                        });

                        chart.$joaninhaObserver.observe(chart.canvas);
                    }

                    document.addEventListener('visibilitychange', chart.$joaninhaOnVisible = () => {
                        if (document.visibilityState === 'visible') {
                            redraw(chart);
                        }
                    });
                },

                afterRender(chart) {
                    chart.canvas?.parentElement?.setAttribute('data-chart-ready', 'true');
                },

                beforeDestroy(chart) {
                    chart.$joaninhaObserver?.disconnect();
                    document.removeEventListener('visibilitychange', chart.$joaninhaOnVisible);
                },
            },
        ];
    })();
</script>
