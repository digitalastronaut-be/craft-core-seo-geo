import "./admin.scss";
import "./vendor/datastar.js";
import "./vendor/datastar-inspector.js";
import "./tooltips.js";
import "./elementIndexScorePolling.js";

import { root } from "./vendor/datastar.js";
import { Chart, registerables } from "chart.js";

window.Datastar = { root };

Chart.register(...registerables);
window.Chart = Chart;
