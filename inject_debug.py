import sys

js_path = 'public/js/admin/pages/revenue.js'
with open(js_path, 'r', encoding='utf-8') as f:
    js_content = f.read()

debug_logic = """
            if (res?.success) {
                const d = res.data;
                renderCards(d);
                renderTheaterPie(d.by_theater);
                renderMovieBar(d.by_movie);
                renderPaymentDonut(d.payment_methods);
                renderTrend(d.by_trend);
                // DEBUG
                if(document.getElementById('cardTotalRevenue')) {
                   console.log('Successfully rendered data');
                }
            } else {
                alert("API returned false success: " + JSON.stringify(res));
            }
        } catch (e) {
            console.error('[Revenue] Error:', e);
            alert("Exception in loadStats: " + e.message);
        } finally {
"""

js_content = js_content.replace("""
            if (res?.success) {
                const d = res.data;
                renderCards(d);
                renderTheaterPie(d.by_theater);
                renderMovieBar(d.by_movie);
                renderPaymentDonut(d.payment_methods);
                renderTrend(d.by_trend);
            }
        } catch (e) {
            console.error('[Revenue] Error:', e);
        } finally {""", debug_logic)

with open(js_path, 'w', encoding='utf-8') as f:
    f.write(js_content)
print("Injected debug alerts into revenue.js")
